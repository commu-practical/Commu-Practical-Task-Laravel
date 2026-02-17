<?php

use App\Http\Controllers\NoticeSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [NoticeSearchController::class, 'index'])->name('notices.index');
Route::match(['get', 'post'], '/search', [NoticeSearchController::class, 'search'])
    ->middleware('throttle:commu-search')
    ->name('notices.search');
