<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationLabelController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\RealtimeController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'twoteam-api',
    ]);
});
Route::get('/cable/events', [RealtimeController::class, 'index']);
Route::post('/cable/presence', [RealtimeController::class, 'presence']);
Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->middleware('signed')->name('attachments.download');
Route::post('/v1/widget/config', [WidgetController::class, 'config']);
Route::get('/v1/widget/conversations', [WidgetController::class, 'conversations']);
Route::post('/v1/widget/conversations', [WidgetController::class, 'createConversation']);
Route::get('/v1/widget/messages', [WidgetController::class, 'messages']);
Route::post('/v1/widget/messages', [WidgetController::class, 'createMessage']);
Route::get('/v1/widget/inbox_members', [WidgetController::class, 'inboxMembers']);
Route::get('/v1/widget/campaigns', [WidgetController::class, 'campaigns']);

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
    Route::post('/v1/accounts/{account}/conversations/{conversation}/toggle_status', [ConversationController::class, 'toggleStatus']);
    Route::post('/v1/accounts/{account}/conversations/{conversation}/toggle_priority', [ConversationController::class, 'togglePriority']);
    Route::get('/v1/accounts/{account}/conversations/{conversation}/attachments', [AttachmentController::class, 'index']);
    Route::apiResource('/v1/accounts/{account}/conversations', ConversationController::class)->except('destroy')->parameters(['conversations' => 'conversation']);
    Route::post('/v1/accounts/{account}/conversations/{conversation}/messages/{message}/retry', [MessageController::class, 'retry']);
    Route::apiResource('/v1/accounts/{account}/conversations/{conversation}/messages', MessageController::class)->except('show')->parameters(['messages' => 'message']);
    Route::post('/v1/accounts/{account}/conversations/{conversation}/assignments', [AssignmentController::class, 'store']);
    Route::get('/v1/accounts/{account}/conversations/{conversation}/labels', [ConversationLabelController::class, 'index']);
    Route::post('/v1/accounts/{account}/conversations/{conversation}/labels', [ConversationLabelController::class, 'store']);
    Route::apiResource('/v1/accounts/{account}/labels', LabelController::class)->parameters(['labels' => 'label']);
});
