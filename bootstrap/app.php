<?php

use App\Domain\Incidents\Events\IncidentApproved;
use App\Domain\Production\Events\BatchClosed;
use App\Domain\Production\Events\DowntimeLogged;
use App\Listeners\UpdateRollupsOnBatchClosed;
use App\Listeners\UpdateRollupsOnDowntimeLogged;
use App\Listeners\UpdateRollupsOnIncidentApproved;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: [
        __DIR__.'/../app/Listeners',
    ])    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('app:evaluate-alerts')->everyFiveMinutes()->withoutOverlapping(15);
        $schedule->command('app:alert-digest')->hourly()->withoutOverlapping(30);
        $schedule->command('app:send-alert-digest')->dailyAt('09:00')->withoutOverlapping(30);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
