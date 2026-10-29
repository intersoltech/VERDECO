<?php

namespace App\Console\Commands;

use App\Services\Alerts\AlertEvaluator;
use App\Services\Alerts\AlertStore;
use Illuminate\Console\Command;

class EvaluateAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:evaluate-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate configured plant and incident alert rules.';

    /**
     * Execute the console command.
     */
    public function handle(AlertEvaluator $evaluator, AlertStore $alertStore): int
    {
        $alerts = $evaluator->evaluate();

        $alertStore->sync($alerts);

        if ($alerts === []) {
            $this->info('No active alerts.');

            return self::SUCCESS;
        }

        foreach ($alerts as $alert) {
            $this->warn(sprintf(
                '[%s] %s | Plant ID %s | %s',
                strtoupper((string) $alert['severity']),
                $alert['rule'],
                $alert['plant_id'],
                $alert['message'],
            ));
        }

        return self::SUCCESS;
    }
}
