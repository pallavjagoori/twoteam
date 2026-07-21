<?php

use App\Http\Controllers\AuthSessionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/sign_in', [AuthSessionController::class, 'store']);
Route::middleware('chatwoot.auth')->group(function () {
    Route::get('/auth/validate_token', [AuthSessionController::class, 'validateToken']);
    Route::delete('/auth/sign_out', [AuthSessionController::class, 'destroy']);
});
