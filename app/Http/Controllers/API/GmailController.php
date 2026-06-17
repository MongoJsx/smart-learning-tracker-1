<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EmailProviderAccount;
use App\Models\User;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Oauth2;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GmailController extends Controller
{
    public function authorizeGmail(Request $request): JsonResponse
    {
        $user = $request->user();
        $client = $this->buildClient($request);
        $returnTo = $this->sanitizeReturnUrl(
            $request->query('return_to'),
            $request->headers->get('origin')
        );
        $state = Crypt::encryptString(json_encode([
            'user_id' => $user->id,
            'timestamp' => now()->timestamp,
            'return_to' => $returnTo,
        ]));

        $client->setState($state);
        $authUrl = $client->createAuthUrl();

        return response()->json(['auth_url' => $authUrl]);
    }

    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->has('error')) {
            return $this->redirectWithStatus(
                $this->sanitizeReturnUrl($request->query('return_to')),
                false,
                $request->get('error') ?: 'gmail_connect_failed'
            );
        }

        $code = $request->get('code');
        $state = $request->get('state');

        if (! $code || ! $state) {
            return response()->json(['message' => 'Missing authorization data.'], 422);
        }

        try {
            $payload = json_decode(Crypt::decryptString($state), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid state token.'], 422);
        }

        $userId = $payload['user_id'] ?? null;
        if (! $userId) {
            return response()->json(['message' => 'Invalid user.'], 422);
        }

        $returnTo = $this->sanitizeReturnUrl($payload['return_to'] ?? null);
        $user = User::find($userId);
        if (! $user) {
            return $returnTo
                ? $this->redirectWithStatus($returnTo, false, 'user_not_found')
                : response()->json(['message' => 'User not found.'], 404);
        }

        try {
            $client = $this->buildClient($request);
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (! empty($token['error'])) {
                $message = $token['error_description'] ?? $token['error'];

                return $returnTo
                    ? $this->redirectWithStatus($returnTo, false, $message)
                    : response()->json(['message' => $message], 400);
            }

            $client->setAccessToken($token);
            $oauth = new Oauth2($client);
            $profile = $oauth->userinfo->get();
            $email = $profile->getEmail() ?: $user->email;

            $expiresAt = null;
            if (isset($token['expires_in'])) {
                $expiresAt = Carbon::now()->addSeconds((int) $token['expires_in']);
            }

            if (Schema::hasTable((new EmailProviderAccount())->getTable())) {
                $existingAccount = EmailProviderAccount::where('user_id', $user->id)
                    ->where('provider', 'gmail')
                    ->where('auth_type', 'oauth')
                    ->first();
                $refreshToken = $token['refresh_token'] ?? $existingAccount?->refresh_token;
                EmailProviderAccount::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'provider' => 'gmail',
                        'auth_type' => 'oauth',
                    ],
                    [
                        'provider_email' => $email,
                        'access_token' => json_encode($token, JSON_UNESCAPED_SLASHES),
                        'refresh_token' => $refreshToken,
                        'token_expires_at' => $expiresAt,
                        'scopes' => isset($token['scope']) ? explode(' ', (string) $token['scope']) : [],
                        'status' => 'active',
                        'last_error' => null,
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::error('Gmail callback failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return $returnTo
                ? $this->redirectWithStatus($returnTo, false, $e->getMessage())
                : response()->json(['message' => $e->getMessage()], 500);
        }

        if ($returnTo) {
            return $this->redirectWithStatus($returnTo, true, null, $email);
        }

        return response()->json([
            'message' => 'Gmail connected.',
            'email' => $email,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! Schema::hasTable((new EmailProviderAccount())->getTable())) {
            return response()->json([
                'connected' => false,
            ]);
        }

        $query = EmailProviderAccount::where('user_id', $user->id)
            ->where('provider', 'gmail')
            ->where('auth_type', 'oauth');

        $account = (clone $query)->where('status', 'active')->first() ?? $query->first();

        if (! $account) {
            return response()->json([
                'connected' => false,
            ]);
        }

        return response()->json([
            'connected' => $account->status === 'active',
            'email' => $account->provider_email,
            'status' => $account->status,
        ]);
    }

    private function buildClient(?Request $request = null): GoogleClient
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = $this->resolveRedirectUri($request);

        $client = new GoogleClient();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->setScopes([
            Gmail::GMAIL_SEND,
            'openid',
            'email',
        ]);

        return $client;
    }

    private function resolveRedirectUri(?Request $request = null): string
    {
        $configured = trim((string) config('services.google.redirect'));
        $appUrl = rtrim((string) config('app.url'), '/');
        $appHost = Str::lower((string) parse_url($appUrl, PHP_URL_HOST));
        $requestHost = Str::lower((string) ($request?->getHost() ?? ''));

        if ($appHost !== '' && in_array($appHost, ['localhost', '127.0.0.1'], true)) {
            return $appUrl.'/api/gmail/callback';
        }

        if ($requestHost !== '' && in_array($requestHost, ['localhost', '127.0.0.1'], true)) {
            return $request->getSchemeAndHttpHost().'/api/gmail/callback';
        }

        if ($configured !== '') {
            return $configured;
        }

        return $appUrl.'/api/gmail/callback';
    }

    private function sanitizeReturnUrl(?string $returnTo, ?string $fallback = null): ?string
    {
        $candidate = trim((string) ($returnTo ?: $fallback ?: ''));
        if ($candidate === '') {
            $candidate = rtrim((string) env('FRONTEND_URL', ''), '/').'/notifications';
        }

        if ($candidate === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $candidate)) {
            return null;
        }

        $parts = parse_url($candidate);
        $host = Str::lower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '/');
        $origin = ($parts['scheme'] ?? 'http').'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');

        $allowedOrigins = collect(explode(',', (string) env('FRONTEND_ORIGINS', '')))
            ->map(fn ($value) => rtrim(trim($value), '/'))
            ->filter()
            ->values();

        $frontendUrl = rtrim((string) env('FRONTEND_URL', ''), '/');
        if ($frontendUrl !== '') {
            $allowedOrigins->push($frontendUrl);
        }

        if (! $allowedOrigins->contains($origin)) {
            return null;
        }

        if ($path === '' || $path === '/') {
            $path = '/notifications';
        }

        return $origin.$path;
    }

    private function redirectWithStatus(
        ?string $returnTo,
        bool $connected,
        ?string $error = null,
        ?string $email = null
    ): JsonResponse|RedirectResponse {
        if (! $returnTo) {
            return response()->json([
                'connected' => $connected,
                'email' => $email,
                'message' => $error,
            ], $connected ? 200 : 400);
        }

        $separator = str_contains($returnTo, '?') ? '&' : '?';
        $params = [
            'gmail_connected' => $connected ? '1' : '0',
        ];

        if ($email) {
            $params['gmail_email'] = $email;
        }

        if ($error) {
            $params['gmail_error'] = $error;
        }

        return redirect()->away($returnTo.$separator.http_build_query($params));
    }
}
