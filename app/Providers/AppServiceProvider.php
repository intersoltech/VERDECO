<?php

namespace App\Providers;

use App\Domain\Incidents\Events\IncidentApproved;
use App\Domain\Integrations\Contracts\WebhookDispatcherInterface;
use App\Domain\Production\Events\BatchClosed;
use App\Domain\Production\Events\DowntimeLogged;
use App\Listeners\UpdateRollupsOnBatchClosed;
use App\Listeners\UpdateRollupsOnDowntimeLogged;
use App\Listeners\UpdateRollupsOnIncidentApproved;
use App\Services\Integrations\WebhookDispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WebhookDispatcherInterface::class, WebhookDispatcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(BatchClosed::class, UpdateRollupsOnBatchClosed::class);
        Event::listen(IncidentApproved::class, UpdateRollupsOnIncidentApproved::class);
        Event::listen(DowntimeLogged::class, UpdateRollupsOnDowntimeLogged::class);
    }
}
