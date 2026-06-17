<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AdminPingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
