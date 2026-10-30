<?php

namespace App\Domain\Production\Events;

use App\Domain\Production\Models\DowntimeEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DowntimeLogged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DowntimeEvent $downtime) {}
}
