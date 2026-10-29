<?php

namespace App\Console\Commands;

use App\Domain\Alerts\Models\Alert;
use Illuminate\Console\Command;

class AlertDigest extends Command
{
    protected $signature = 'app:alert-digest';

    protected $description = 'Summarize open operational alerts.';

    public function handle(): int
    {
        $alerts = Alert::query()->where('status', 'open')->orderBy('severity')->orderBy('triggered_at')->get();

        if ($alerts->isEmpty()) {
            $this->info('No open alerts.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Open alerts: %d', $alerts->count()));

        foreach ($alerts as $alert) {
            $this->line(sprintf(
                '[%s] Plant %s | %s | %s',
                strtoupper($alert->severity),
                $alert->plant?->code ?? 'Unknown',
                $alert->rule,
                $alert->message
            ));
        }

        return self::SUCCESS;
    }
}
