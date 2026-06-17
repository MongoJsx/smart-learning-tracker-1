<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($user);
    }

    public function googleConfig(): JsonResponse
    {
        return response()->json([
            'client_id' => $this->getGoogleClientId(),
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'education_level' => $data['education_level'] ?? null,
            ]);

            $token = $user->createToken('api')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user,
            ], 201);
        } catch (QueryException $e) {
            report($e);
            return response()->json([
                'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่อีกครั้ง',
            ], 503);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'ไม่สามารถสมัครสมาชิกได้ กรุณาลองใหม่อีกครั้ง',
            ], 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();
            $user = User::where('email', $credentials['email'])->first();
            if (!$user) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are invalid.'],
                ]);
            }

            $password = $credentials['password'];
            $stored = $user->password ?? '';
            $valid = false;
            if ($stored !== '') {
                try {
                    $valid = Hash::check($password, $stored);
                } catch (Throwable $e) {
                    // Some legacy databases store passwords as plain text or use a different hash algorithm.
                    // Treat it as invalid here so we can optionally fall back (dev only) below.
                    $valid = false;
                }
            }

            // ✅ รองรับฐานข้อมูลเก่าที่เก็บรหัสผ่านเป็น plain text (เฉพาะโหมด dev)
            if (! $valid && $this->allowPlainPassword() && $stored !== '') {
                $valid = hash_equals($stored, $password);
                if ($valid) {
                    // อัปเกรดเป็น hash ให้เรียบร้อย
                    $user->password = Hash::make($password);
                    $user->save();
                }
            }

            if (! $valid) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are invalid.'],
                ]);
            }

            $token = $user->createToken('api')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่อีกครั้ง',
            ], 500);
        }
    }

    public function googleSignIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        $clientId = $this->getGoogleClientId();
        if (!$clientId) {
            throw ValidationException::withMessages([
                'credential' => ['Google Sign-In is not configured (missing GOOGLE_CLIENT_ID).'],
            ]);
        }

        try {
            $payload = null;
            try {
                $resp = Http::asForm()->get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $data['credential'],
                ]);
                if ($resp->ok()) {
                    $payload = $resp->json();
                }
            } catch (Throwable $e) {
                report($e);
            }

            $payload = $this->resolveGooglePayload($payload, $data['credential'], $clientId);
            if (!$payload || empty($payload['email'])) {
                Log::error('Invalid Google payload', ['payload' => $payload]);
                throw ValidationException::withMessages([
                    'credential' => ['ไม่สามารถยืนยันตัวตนจาก Google ได้'],
                ]);
            }

            // Create or update user
            $user = User::firstOrNew(['email' => $payload['email']]);

            if (!$user->exists) {
                $user->password = Hash::make(Str::random(40));
                if (empty($user->role)) {
                    $user->role = 'user';
                }
            }

            // Check if admin
            if ($this->isAdminEmail($payload['email'])) {
                $user->role = 'admin';
            }

            // Update user fields
            $user->fill([
                'name'        => $payload['name'] ?? $payload['email'],
                'profile_pic' => $payload['picture'] ?? $user->profile_pic,
                'provider'    => 'google',
                'provider_id' => $payload['sub'] ?? $payload['iss'] ?? '',
                'email_verified_at' => now(),
            ]);

            if (Schema::hasColumn('users', 'google_id')) {
                $user->google_id = $payload['sub'] ?? $user->google_id;
            }

            $user->save();

            // ลบ token เก่า กันสลับ user แล้ว token ค้าง
            $user->tokens()->delete();

            return $this->issueToken($user);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'ไม่สามารถยืนยันตัวตนจาก Google ได้',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            report($e);
            return response()->json([
                'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้',
            ], 503);
        } catch (Throwable $e) {
            report($e);
            Log::error('Google login error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'ไม่สามารถเข้าสู่ระบบด้วย Google ได้',
            ], 500);
        }
    }

    public function devLogin(Request $request): JsonResponse
    {
        if (! $this->devLoginEnabled()) {
            return response()->json(['message' => 'Dev login is disabled'], 403);
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::firstOrNew(['email' => $data['email']]);

        if (! $user->exists) {
            $user->password = Hash::make(Str::random(40));
            if (empty($user->role)) {
                $user->role = 'user';
            }
        }

        if ($this->isAdminEmail($data['email'])) {
            $user->role = 'admin';
        }

        if (empty($user->name)) {
            $user->name = $data['email'];
        }

        $user->save();

        return $this->issueToken($user);
    }

    public function googleCallback(Request $request): JsonResponse
    {
        // In production, validate the state token
        $code = $request->input('code');

        if (!$code) {
            return response()->json(['message' => 'Authorization code is missing'], 400);
        }

        // Exchange code for token
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->ok()) {
            return response()->json(['message' => 'Failed to exchange code for token'], 400);
        }

        $token = $response->json()['id_token'] ?? null;

        if (!$token) {
            return response()->json(['message' => 'No ID token received'], 400);
        }

        return response()->json(['credential' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $user->tokens()->delete();

            return response()->json(status: 204);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'ไม่สามารถออกจากระบบได้ กรุณาลองใหม่อีกครั้ง',
            ], 500);
        }
    }

    public function updateProfile(ProfileUpdateRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $data = $request->validated();

            if (array_key_exists('name', $data)) {
                $data['name'] = trim((string) $data['name']);
            }

            if (array_key_exists('education_level', $data)) {
                $educationLevel = trim((string) ($data['education_level'] ?? ''));
                $data['education_level'] = $educationLevel !== '' ? $educationLevel : null;
            }

            if (array_key_exists('email', $data)) {
                $data['email'] = strtolower(trim((string) $data['email']));
            }

            if ($request->hasFile('profile_pic')) {
                $path = $request->file('profile_pic')->store('profile-pictures', 'public');
                $data['profile_pic'] = '/storage/' . ltrim($path, '/');
            }

            $user->forceFill($data)->save();

            return response()->json([
                'message' => 'บันทึกข้อมูลโปรไฟล์เรียบร้อยแล้ว',
                'user' => $user->fresh(),
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'ไม่สามารถอัปเดตโปรไฟล์ได้',
            ], 500);
        }
    }

    private function issueToken(User $user): JsonResponse
    {
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    private function allowInsecureGoogleToken(): bool
    {
        return filter_var(env('GOOGLE_ALLOW_INSECURE_TOKEN', false), FILTER_VALIDATE_BOOL);
    }

    private function allowPlainPassword(): bool
    {
        $flag = env('ALLOW_PLAIN_PASSWORD', null);
        if ($flag === null) {
            return config('app.env') === 'local';
        }
        return filter_var($flag, FILTER_VALIDATE_BOOL);
    }

    private function devLoginEnabled(): bool
    {
        return filter_var(env('DEV_LOGIN_ENABLED', false), FILTER_VALIDATE_BOOL);
    }

    private function resolveGooglePayload($payload, string $credential, ?string $clientId): ?array
    {
        if ($this->googlePayloadIsValid($payload, $clientId)) {
            return $payload;
        }

        if (!$this->allowInsecureGoogleToken()) {
            return null;
        }

        $decoded = $this->decodeGoogleJwtPayload($credential);
        return $this->googlePayloadIsValid($decoded, $clientId) ? $decoded : null;
    }

    private function googlePayloadIsValid($payload, ?string $clientId): bool
    {
        if (!is_array($payload) || empty($payload['email'])) {
            return false;
        }
        if (isset($payload['exp']) && time() > (int) $payload['exp']) {
            return false;
        }
        if (isset($payload['iss'])) {
            $allowed = ['accounts.google.com', 'https://accounts.google.com'];
            if (!in_array($payload['iss'], $allowed, true)) {
                return false;
            }
        }
        if ($clientId && isset($payload['aud']) && $payload['aud'] !== $clientId) {
            return false;
        }
        if (isset($payload['email_verified']) && $payload['email_verified'] !== true && $payload['email_verified'] !== 'true') {
            return false;
        }
        return true;
    }

    private function decodeGoogleJwtPayload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = $this->base64UrlDecode($parts[1]);
        if (!$payload) {
            return null;
        }

        $json = json_decode($payload, true);
        return $json && is_array($json) ? $json : null;
    }

    private function base64UrlDecode(string $data): ?string
    {
        $data = str_replace(['-', '_'], ['+', '/'], $data);
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($data, true);
        return $decoded !== false ? $decoded : null;
    }

    private function isAdminEmail(string $email): bool
    {
        $raw = env('ADMIN_EMAILS', '');
        if (!is_string($raw) || trim($raw) === '') {
            return false;
        }

        $needle = strtolower(trim($email));
        $list = array_filter(array_map('trim', explode(',', $raw)));
        foreach ($list as $item) {
            if ($needle === strtolower($item)) {
                return true;
            }
        }

        return false;
    }

    private function getGoogleClientId(): ?string
    {
        $primary = trim((string) config('services.google.client_id'));
        if ($primary !== '') {
            return $primary;
        }

        $fallback = trim((string) env('VITE_GOOGLE_CLIENT_ID', ''));
        if ($fallback !== '') {
            return $fallback;
        }

        return $this->readEnvValueFromFrontendFile('VITE_GOOGLE_CLIENT_ID');
    }

    private function readEnvValueFromFrontendFile(string $key): ?string
    {
        $envPath = base_path('frontend/.env');
        if (!is_file($envPath) || !is_readable($envPath)) {
            return null;
        }

        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return null;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            if (!str_starts_with($trimmed, $key . '=')) {
                continue;
            }

            $value = trim(substr($trimmed, strlen($key) + 1));
            $value = trim($value, "\"'");
            return $value !== '' ? $value : null;
        }

        return null;
    }
}
