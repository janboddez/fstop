<?php

use App\Jobs\ProcessWebmention;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $webmentions = DB::select(
        'SELECT * FROM webmentions WHERE status = ? ORDER BY created_at ASC LIMIT ?',
        ['new', 50]
    );

    foreach ($webmentions as $webmention) {
        ProcessWebmention::dispatch($webmention);
    }
})->everyFiveMinutes();
