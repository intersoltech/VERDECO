<?php

namespace App\Listeners;

use App\Domain\Production\Events\BatchClosed;
use App\Services\Reporting\FactRollupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateRollupsOnBatchClosed implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'rollups';

    public int $tries = 3;

    public array $backoff = [5, 15, 30];

    public function __construct(private readonly FactRollupService $rollupService) {}

    public function handle(BatchClosed $event): void
    {
        $this->rollupService->rollupBatch($event->batch);
    }
}
