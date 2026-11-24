<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bcv:sync-rate')
    ->weekdays()
    ->cron('0 9,13,17 * * 1-5')
    ->withoutOverlapping();
