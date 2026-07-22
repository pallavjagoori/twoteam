<?php

use App\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/widget', [WidgetController::class, 'page']);
