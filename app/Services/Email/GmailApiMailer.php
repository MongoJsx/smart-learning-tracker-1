<?php

namespace App\Services\Email;

use App\Models\EmailProviderAccount;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use RuntimeException;

class GmailApiMailer
{
    public function send(
        EmailProviderAccount $account,
        string $toEmail,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        ?string $fromName = null
    ): string {
        $client = $this->buildClient($account);
        $service = new Gmail($client);

        $raw = $this->buildRawMessage(
            $account->provider_email,
            $toEmail,
            $subject,
            $htmlBody,
            $textBody,
            $fromName
        );

        $message = new Message();
        $message->setRaw($raw);

        $response = $service->users_messages->send('me', $message);

        $account->update([
            'status' => 'active',
            'last_error' => null,
        ]);

        return (string) ($response->getId() ?? '');
    }

    private function buildClient(EmailProviderAccount $account): GoogleClient
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        if (! filled($clientId) || ! filled($clientSecret)) {
            throw new RuntimeException('Google client_id/client_secret not configured.');
        }

        $client = new GoogleClient();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setAccessType('offline');

        $token = $this->normalizeAccessToken($account->access_token);
        if (! isset($token['access_token'])) {
            throw new RuntimeException('Missing Gmail access token.');
        }

        $client->setAccessToken($token);

        if ($this->isTokenExpired($account, $token)) {
            $this->refreshToken($client, $account);
        }

        return $client;
    }

    private function normalizeAccessToken(mixed $token): array
    {
        if (is_array($token)) {
            return $token;
        }

        if (! is_string($token) || $token === '') {
            return [];
        }

        $decoded = json_decode($token, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return ['access_token' => $token];
    }

    private function isTokenExpired(EmailProviderAccount $account, array $token): bool
    {
        if ($account->token_expires_at) {
            return Carbon::now()->greaterThanOrEqualTo($account->token_expires_at);
        }

        if (isset($token['expires_in'], $token['created'])) {
            $expiresAt = (int) $token['created'] + (int) $token['expires_in'] - 60;
            return time() >= $expiresAt;
        }

        return false;
    }

    private function refreshToken(GoogleClient $client, EmailProviderAccount $account): void
    {
        if (! $account->refresh_token) {
            $account->update([
                'status' => 'error',
                'last_error' => 'Missing Gmail refresh token.',
            ]);
            throw new RuntimeException('Missing Gmail refresh token.');
        }

        $newToken = $client->fetchAccessTokenWithRefreshToken($account->refresh_token);

        if (! empty($newToken['error'])) {
            $message = $newToken['error_description'] ?? $newToken['error'];
            $account->update([
                'status' => 'error',
                'last_error' => $message,
            ]);
            throw new RuntimeException('Gmail token refresh failed: '.$message);
        }

        $client->setAccessToken($newToken);

        $expiresAt = null;
        if (isset($newToken['expires_in'])) {
            $expiresAt = Carbon::now()->addSeconds((int) $newToken['expires_in']);
        }

        $account->update([
            'access_token' => $this->encodeToken($newToken),
            'refresh_token' => $newToken['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => $expiresAt,
            'status' => 'active',
            'last_error' => null,
        ]);
    }

    private function encodeToken(array $token): string
    {
        return json_encode($token, JSON_UNESCAPED_SLASHES);
    }

    private function buildRawMessage(
        string $fromEmail,
        string $toEmail,
        string $subject,
        string $htmlBody,
        ?string $textBody,
        ?string $fromName
    ): string {
        $boundary = 'gmail-'.bin2hex(random_bytes(12));
        $encodedSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';
        $fromLabel = $fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail;

        $headers = [
            "From: {$fromLabel}",
            "To: {$toEmail}",
            "Subject: {$encodedSubject}",
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ];

        $plain = $textBody ?: strip_tags($htmlBody);
        $parts = [
            "--{$boundary}",
            'Content-Type: text/plain; charset=UTF-8',
            '',
            $plain,
            "--{$boundary}",
            'Content-Type: text/html; charset=UTF-8',
            '',
            $htmlBody,
            "--{$boundary}--",
        ];

        $rawMessage = implode("\r\n", $headers)."\r\n\r\n".implode("\r\n", $parts);

        return rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');
    }
}
