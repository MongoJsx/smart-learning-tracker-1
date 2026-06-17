<?php

use App\Http\Controllers\API\Admin\AdminPingController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('abilities:admin')->group(function () {
    Route::get('/ping', AdminPingController::class);
});
