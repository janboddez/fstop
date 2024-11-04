<?php

use App\Http\Controllers\EntryController;
use App\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])
    ->prefix('listens')
    ->name('listens.')
    ->group(function () {
        Route::get('/', [EntryController::class, 'index'])
            ->name('index');

        Route::get('feed', FeedController::class)
            ->name('feed');

        Route::get('{slug}', [EntryController::class, 'show'])
            ->name('show');
    });
