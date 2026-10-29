<?php

namespace App\Console\Commands;

use App\Domain\Alerts\Models\Alert;
use App\Notifications\AlertDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendAlertDigest extends Command
{
    protected $signature = 'app:send-alert-digest';

    protected $description = 'Send a summary email for all open alerts.';

    public function handle(): int
    {
        $alerts = Alert::query()
            ->with('plant')
            ->where('status', 'open')
            ->orderBy('severity')
            ->orderBy('triggered_at')
            ->get()
            ->map(fn (Alert $alert) => [
                'severity' => $alert->severity,
                'plant_code' => $alert->plant?->code ?? 'Unknown plant',
                'message' => $alert->message,
            ])
            ->all();

        if ($alerts === []) {
            $this->info('No open alerts to digest.');

            return self::SUCCESS;
        }

        Notification::route('mail', 'admin@verdeco.local')
            ->notify(new AlertDigestNotification($alerts));

        $this->info(sprintf('Sent digest for %d open alert(s).', count($alerts)));

        return self::SUCCESS;
    }
}
