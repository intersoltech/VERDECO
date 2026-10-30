<?php

namespace App\Domain\Incidents\Events;

use App\Domain\Incidents\Models\CostIncident;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CostIncident $incident) {}
}
