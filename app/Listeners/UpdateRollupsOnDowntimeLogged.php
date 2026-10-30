<?php

namespace App\Listeners;

use App\Domain\Production\Events\DowntimeLogged;
use App\Services\Reporting\FactRollupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateRollupsOnDowntimeLogged implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'rollups';

    public int $tries = 3;

    public array $backoff = [5, 15, 30];

    public function __construct(private readonly FactRollupService $rollupService) {}

    public function handle(DowntimeLogged $event): void
    {
        $this->rollupService->rollupDowntime($event->downtime);
    }
}
