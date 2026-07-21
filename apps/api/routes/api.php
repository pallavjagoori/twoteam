<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\TeamController;
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
    Route::apiResource('/v1/accounts/{account}/agents', AgentController::class)->except('show')->parameters(['agents' => 'agent']);
    Route::apiResource('/v1/accounts/{account}/teams', TeamController::class)->parameters(['teams' => 'team']);
    Route::get('/v1/accounts/{account}/contacts/search', [ContactController::class, 'search']);
    Route::apiResource('/v1/accounts/{account}/contacts', ContactController::class)->parameters(['contacts' => 'contact']);
    Route::post('/v1/accounts/{account}/inboxes/{inbox}/reset_secret', [InboxController::class, 'resetSecret']);
    Route::apiResource('/v1/accounts/{account}/inboxes', InboxController::class)->parameters(['inboxes' => 'inbox']);
});
