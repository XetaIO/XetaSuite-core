<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Demo Mode Scheduler
|--------------------------------------------------------------------------
|
| When demo mode is enabled, reset the database every 6 hours to ensure
| a clean state for demo users.
|
*/
if (config('app.demo_mode')) {
    Schedule::command('demo:reset')
        ->everySixHours()
        ->runInBackground()
        ->withoutOverlapping();
}
