<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Operational monitoring & Alert evaluation
Schedule::command('app:evaluate-alerts')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Daily digest for plant managers and group controller
Schedule::command('app:send-alert-digest')
    ->dailyAt('06:00')
    ->onOneServer();

// Nightly rollup check & fact table maintenance
Schedule::command('rollups:rebuild')
    ->dailyAt('01:00')
    ->onOneServer();
