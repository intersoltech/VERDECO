<?php

namespace App\Console\Commands;

use App\Domain\Reporting\Models\FactDailyLineCost;
use App\Domain\Reporting\Models\FactDailyPlantKpi;
use App\Domain\Reporting\Models\FactIncidentSummary;
use App\Domain\Reporting\Models\FactMonthlyVariance;
use App\Services\Reporting\FactRollupService;
use Illuminate\Console\Command;

class RebuildRollups extends Command
{
    protected $signature = 'rollups:rebuild 
                            {--plant= : Plant ID to scope rollup rebuild} 
                            {--start= : Start date (YYYY-MM-DD)} 
                            {--end= : End date (YYYY-MM-DD)}
                            {--fresh : Truncate fact tables before rebuild}';

    protected $description = 'Idempotently rebuild fact rollup tables from raw operational events';

    public function handle(FactRollupService $rollupService): int
    {
        $plantId = $this->option('plant') ? (int) $this->option('plant') : null;
        $start = $this->option('start');
        $end = $this->option('end');

        if ($this->option('fresh')) {
            $this->warn('Truncating fact tables before rebuild...');
            if ($plantId) {
                FactDailyLineCost::where('plant_id', $plantId)->delete();
                FactDailyPlantKpi::where('plant_id', $plantId)->delete();
                FactMonthlyVariance::where('plant_id', $plantId)->delete();
                FactIncidentSummary::where('plant_id', $plantId)->delete();
            } else {
                FactDailyLineCost::truncate();
                FactDailyPlantKpi::truncate();
                FactMonthlyVariance::truncate();
                FactIncidentSummary::truncate();
            }
        }

        $this->info('Rebuilding fact rollup tables...');

        $stats = $rollupService->rebuild($plantId, $start, $end);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Batches Processed', $stats['batches_processed']],
                ['Incidents Processed', $stats['incidents_processed']],
                ['Downtime Events Processed', $stats['downtimes_processed']],
                ['Fact Daily Line Rows', $stats['fact_daily_line_count']],
                ['Fact Daily Plant KPI Rows', $stats['fact_daily_plant_count']],
                ['Fact Monthly Variance Rows', $stats['fact_monthly_variance_count']],
                ['Fact Incident Summary Rows', $stats['fact_incident_summary_count']],
            ]
        );

        $this->info('Fact rollup tables rebuild completed successfully.');

        return self::SUCCESS;
    }
}
