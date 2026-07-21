<?php

use App\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'twoteam-api',
    ]);
});

Route::middleware('chatwoot.auth')->group(function () {
    Route::get('/v1/accounts/{account}', [AccountController::class, 'show']);
    Route::patch('/v1/accounts/{account}', [AccountController::class, 'update']);
    Route::put('/v1/accounts/{account}', [AccountController::class, 'update']);
    Route::post('/v1/accounts/{account}/update_active_at', [AccountController::class, 'active']);
});
