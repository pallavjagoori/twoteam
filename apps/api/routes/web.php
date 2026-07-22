<?php

use App\Http\Controllers\FrontendController;
use App\Http\Middleware\RequestContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app/login');
Route::get('/app/{path?}', [FrontendController::class, 'app'])
    ->where('path', '.*')
    ->middleware([RequestContext::class, SecurityHeaders::class]);
