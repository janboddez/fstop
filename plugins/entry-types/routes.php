<?php

use App\Http\Controllers\EntryController;
use App\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;

foreach (['note', 'like', 'listen'] as $type) {
    Route::middleware(['web'])
        ->get(Str::plural($type) . '/feed', FeedController::class)
        ->name(Str::plural($type) . '.feed');

    Route::middleware(['web'])
        ->get(Str::plural($type), [EntryController::class, 'index'])
        ->name(Str::plural($type) . '.index');

    Route::middleware(['web'])
        ->get(Str::plural($type) . '/{slug}', [EntryController::class, 'show'])
        ->name(Str::plural($type) . '.show');
}
