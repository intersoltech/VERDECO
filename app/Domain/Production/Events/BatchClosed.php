<?php

namespace App\Domain\Production\Events;

use App\Domain\Production\Models\ProductionBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchClosed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ProductionBatch $batch) {}
}
