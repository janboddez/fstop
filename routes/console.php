<?php

use App\Jobs\ProcessWebmentions;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ProcessWebmentions())
    ->everyFiveMinutes();
