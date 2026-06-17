<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class GoogleAuthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        $clientId = config('services.google.client_id');
        if (!$clientId) {
            throw ValidationException::withMessages([
                'credential' => ['Google Sign-In is not configured (missing GOOGLE_CLIENT_ID).'],
            ]);
        }

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
            throw ValidationException::withMessages([
                'credential' => ['ไม่สามารถยืนยันตัวตนจาก Google ได้'],
            ]);
        }

        try {
            $user = $this->upsertUserFromGoogle($payload);

            // ลบ token เก่า กันสลับ user แล้ว token ค้าง
            $user->tokens()->delete();

            // ให้ abilities ตาม role (admin/user)
            $abilities = ($user->role ?? 'user') === 'admin' ? ['admin'] : ['user'];

            $token = $user->createToken('api', $abilities)->plainTextToken;

            return response()->json([
                'token' => $token,
                'user'  => $user->fresh(),
            ]);
        } catch (QueryException $e) {
            report($e);
            return response()->json([
                'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่อีกครั้ง',
            ], 503);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่อีกครั้ง',
            ], 500);
        }
    }

    private function upsertUserFromGoogle(array $payload): User
    {
        $email = $payload['email'];
        $name = $payload['name'] ?? $email;
        $picture = $payload['picture'] ?? null;
        $googleId = $payload['sub'] ?? null;

        $user = User::firstOrNew(['email' => $email]);

        if (!$user->exists) {
            // ให้ password สุ่มไว้กัน null พัง validation/guard บางแบบ
            $user->password = Hash::make(Str::random(40));
            if (empty($user->role)) {
                $user->role = 'user';
            }
        }

        $user->forceFill([
            'name' => $name,
            'profile_pic' => $picture ?: $user->profile_pic,
            'provider' => 'google',
            'provider_id' => $googleId ?: $user->provider_id,
        ]);

        // ถ้ามีคอลัมน์ google_id ก็ใส่
        if (property_exists($user, 'google_id') || Schema::hasColumn('users', 'google_id')) {
            $user->google_id = $googleId ?: $user->google_id;
        }

        // promote เป็น admin ถ้า email อยู่ใน ADMIN_EMAILS
        if ($this->isAdminEmail($email)) {
            $user->role = 'admin';
        }

        if (is_null($user->email_verified_at ?? null)) {
            $user->email_verified_at = now();
        }

        $user->save();

        return $user;
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

    private function allowInsecureGoogleToken(): bool
    {
        $flag = env('GOOGLE_ALLOW_INSECURE_TOKEN', false);
        if (is_bool($flag)) {
            return $flag;
        }
        $flag = strtolower(trim((string) $flag));
        return in_array($flag, ['1', 'true', 'yes'], true);
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
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : null;
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

    private function base64UrlDecode(string $value)
    {
        $value = str_replace(['-', '_'], ['+', '/'], $value);
        $pad = strlen($value) % 4;
        if ($pad) {
            $value .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($value);
    }

    private function isAdminEmail(?string $email): bool
    {
        if (! $email) {
            return false;
        }

        $raw = env('ADMIN_EMAILS', '');
        if (! is_string($raw) || trim($raw) === '') {
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
}
