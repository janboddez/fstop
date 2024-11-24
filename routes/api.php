<?php

use App\Http\Controllers\Micropub\MicropubController;
use Illuminate\Support\Facades\Route;

Route::prefix('micropub')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [MicropubController::class, 'get']);
        Route::post('/', [MicropubController::class, 'post'])
            ->middleware(App\Http\Middleware\ConvertToJsonSyntax::class);

        Route::post('/media', [MicropubController::class, 'media'])
            ->name('micropub.media-endpoint');
    });
