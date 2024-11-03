<?php

use App\Http\Controllers\EntryController;
use App\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])
    ->get('listens/feed', FeedController::class)
    ->name('listens.feed');

Route::middleware(['web'])
    ->get('listens', [EntryController::class, 'index'])
    ->name('listens.index');

Route::middleware(['web'])
    ->get('listens/{slug}', [EntryController::class, 'show'])
    ->name('listens.show');
