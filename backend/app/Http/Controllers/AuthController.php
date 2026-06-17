<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * ✅ Email/Password login (ถ้าคุณมีฟอร์ม login แบบธรรมดา)
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['อีเมลหรือรหัสผ่านไม่ถูกต้อง'],
            ]);
        }

        return $this->issueToken($user);
    }

    /**
     * ✅ DEV LOGIN: ส่ง email มาก็ออก token ให้ (ใช้ตอน dev เท่านั้น)
     * POST /api/auth/dev-login { email }
     */
    public function devLogin(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        return $this->issueToken($user);
    }

    /**
     * ✅ Google Sign-In: รับ credential (id_token) จากฝั่ง Frontend
     * POST /api/auth/google { credential }
     */
    public function googleSignIn(Request $request)
    {
        $data = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        $clientId = config('services.google.client_id');
        if (! $clientId) {
            throw ValidationException::withMessages([
                'credential' => ['Google Sign-In is not configured (missing GOOGLE_CLIENT_ID).'],
            ]);
        }

        // 1) verify id_token ผ่าน tokeninfo (ไม่ต้องใช้ google/apiclient)
        try {
            $resp = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $data['credential'],
            ]);

            if (! $resp->ok()) {
                throw ValidationException::withMessages([
                    'credential' => ['ไม่สามารถยืนยันตัวตนจาก Google ได้'],
                ]);
            }

            $payload = $resp->json();

            // aud ต้องตรง client_id
            if (($payload['aud'] ?? null) !== $clientId) {
                throw ValidationException::withMessages([
                    'credential' => ['Google token ไม่ตรงกับแอปนี้ (aud mismatch)'],
                ]);
            }

            if (empty($payload['email'])) {
                throw ValidationException::withMessages([
                    'credential' => ['ไม่พบอีเมลใน Google token'],
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages([
                'credential' => ['ไม่สามารถยืนยันตัวตนจาก Google ได้'],
            ]);
        }

        // 2) upsert user
        $user = User::firstOrNew(['email' => $payload['email']]);

        if (! $user->exists) {
            // ให้มีรหัสผ่านสุ่มไว้ เผื่ออนาคตอยากเปลี่ยนมา login แบบปกติ
            $user->password = Hash::make(Str::random(40));
        }

        $user->forceFill([
            'name'        => $payload['name'] ?? $payload['email'],
            'profile_pic' => $payload['picture'] ?? $user->profile_pic,
            'provider'    => 'google',
            'provider_id' => $payload['sub'] ?? $user->provider_id,
        ]);

        if (is_null($user->email_verified_at)) {
            $user->email_verified_at = now();
        }

        // 3) ตั้ง role admin จาก whitelist ใน .env (ADMIN_EMAILS)
        $adminEmails = collect(explode(',', (string) env('ADMIN_EMAILS')))
            ->map(fn ($x) => trim($x))
            ->filter()
            ->values();

        // ถ้าอีเมลอยู่ใน whitelist => admin
        // ถ้าไม่อยู่ => user (หรือรักษา role เดิมถ้ามีอยู่แล้ว)
        if ($adminEmails->contains($user->email)) {
            $user->role = 'admin';
        } else {
            $user->role = $user->role ?: 'user';
        }

        $user->save();

        return $this->issueToken($user);
    }

    /**
     * ✅ Logout: ลบ token ปัจจุบัน
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'logged out']);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'education_level' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $user->forceFill($data)->save();

        return response()->json($user->fresh());
    }

    /**
     * ✅ helper: ออก token พร้อม abilities
     */
    private function issueToken(User $user)
    {
        // กัน token ค้าง
        $user->tokens()->delete();

        // ออกสิทธิ์ตาม role
        $abilities = ($user->role === 'admin') ? ['admin'] : ['user'];

        $token = $user->createToken('api', $abilities)->plainTextToken;

        return response()->json([
            'user'  => $user->fresh(),
            'token' => $token,
        ]);
    }
}
