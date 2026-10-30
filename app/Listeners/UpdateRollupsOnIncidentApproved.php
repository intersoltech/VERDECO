<?php

namespace App\Listeners;

use App\Domain\Incidents\Events\IncidentApproved;
use App\Services\Reporting\FactRollupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateRollupsOnIncidentApproved implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'rollups';

    public int $tries = 3;

    public array $backoff = [5, 15, 30];

    public function __construct(private readonly FactRollupService $rollupService) {}

    public function handle(IncidentApproved $event): void
    {
        $this->rollupService->rollupIncident($event->incident);
    }
}
