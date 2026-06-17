<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function googleSignIn(Request $request)
{
    $request->validate([
        'credential' => 'required|string'
    ]);

    $clientId = config('services.google.client_id');

    if (!$clientId) {
        return response()->json([
            'message' => 'Google client id not configured'
        ], 500);
    }

    // 🔥 ตรวจ token กับ Google
    $google = Http::get('https://oauth2.googleapis.com/tokeninfo', [
        'id_token' => $request->credential,
    ])->json();

    if (isset($google['error_description'])) {
        return response()->json([
            'message' => 'Invalid Google token',
            'google_error' => $google
        ], 401);
    }

    // 🔥 เช็คว่า token ออกให้ client นี้จริงไหม
    if ($google['aud'] !== $clientId) {
        return response()->json([
            'message' => 'Client ID mismatch'
        ], 401);
    }

    // 🔥 สร้างหรืออัปเดต user
    $user = User::updateOrCreate(
        ['email' => $google['email']],
        [
            'name' => $google['name'] ?? 'Google User',
            'google_id' => $google['sub'],
            'avatar' => $google['picture'] ?? null,
            'password' => bcrypt(Str::random(16)),
        ]
    );

    // 🔥 สร้าง token สำหรับระบบคุณ
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => $user
    ]);
}
}
