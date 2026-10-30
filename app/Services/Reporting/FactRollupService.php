<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Domain\Costing\Models\StandardCost;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\DowntimeEvent;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Reporting\Models\FactDailyLineCost;
use App\Domain\Reporting\Models\FactDailyPlantKpi;
use App\Domain\Reporting\Models\FactIncidentSummary;
use App\Domain\Reporting\Models\FactMonthlyVariance;
use App\Services\Costing\DecimalMath;
use App\Services\Costing\VarianceAnalyzer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class FactRollupService
{
    public function __construct(
        private readonly VarianceAnalyzer $varianceAnalyzer = new VarianceAnalyzer()
    ) {}

    public function rollupBatch(ProductionBatch $batch): void
    {
        if (!$batch->plant_id || !$batch->line_id || !$batch->batch_date) {
            return;
        }

        $date = Carbon::parse($batch->batch_date)->toDateString();
        $month = Carbon::parse($batch->batch_date)->startOfMonth()->toDateString();

        $this->updateDailyLineCost((int) $batch->tenant_id, (int) $batch->plant_id, (int) $batch->line_id, $date);
        $this->updateDailyPlantKpi((int) $batch->tenant_id, (int) $batch->plant_id, $date);
        $this->updateMonthlyVariance((int) $batch->tenant_id, (int) $batch->plant_id, $month);
    }

    public function rollupIncident(CostIncident $incident): void
    {
        if (!$incident->plant_id || !$incident->occurred_at) {
            return;
        }

        $date = Carbon::parse($incident->occurred_at)->toDateString();

        FactIncidentSummary::updateOrCreate(
            [
                'tenant_id' => $incident->tenant_id,
                'plant_id' => $incident->plant_id,
                'incident_date' => $date,
                'incident_type' => $incident->incident_type,
                'root_cause_category' => $incident->root_cause_category,
            ],
            [
                'cash_cost' => DB::raw("(SELECT COALESCE(SUM(cash_cost), 0) FROM cost_incidents WHERE plant_id = {$incident->plant_id} AND DATE(occurred_at) = '{$date}' AND incident_type = '{$incident->incident_type}' AND root_cause_category = '{$incident->root_cause_category}')"),
                'opportunity_cost' => DB::raw("(SELECT COALESCE(SUM(opportunity_cost), 0) FROM cost_incidents WHERE plant_id = {$incident->plant_id} AND DATE(occurred_at) = '{$date}' AND incident_type = '{$incident->incident_type}' AND root_cause_category = '{$incident->root_cause_category}')"),
                'incident_count' => DB::raw("(SELECT COUNT(*) FROM cost_incidents WHERE plant_id = {$incident->plant_id} AND DATE(occurred_at) = '{$date}' AND incident_type = '{$incident->incident_type}' AND root_cause_category = '{$incident->root_cause_category}')"),
            ]
        );

        $this->updateDailyPlantKpi((int) $incident->tenant_id, (int) $incident->plant_id, $date);
    }

    public function rollupDowntime(DowntimeEvent $downtime): void
    {
        if (!$downtime->plant_id || !$downtime->start_at) {
            return;
        }

        $date = Carbon::parse($downtime->start_at)->toDateString();

        if ($downtime->line_id) {
            $this->updateDailyLineCost((int) $downtime->tenant_id, (int) $downtime->plant_id, (int) $downtime->line_id, $date);
        }

        $this->updateDailyPlantKpi((int) $downtime->tenant_id, (int) $downtime->plant_id, $date);
    }

    public function updateDailyLineCost(int $tenantId, int $plantId, int $lineId, string $date): void
    {
        $batchAgg = ProductionBatch::query()
            ->where('plant_id', $plantId)
            ->where('line_id', $lineId)
            ->whereDate('batch_date', $date)
            ->selectRaw('
                COUNT(*) as batch_count,
                COALESCE(SUM(input_kg), 0) as input_kg,
                COALESCE(SUM(good_output_kg), 0) as output_kg,
                COALESCE(SUM(material_cost), 0) as material_cost,
                COALESCE(SUM(labor_cost), 0) as labor_cost,
                COALESCE(SUM(utility_cost), 0) as utility_cost,
                COALESCE(SUM(overhead_cost), 0) as overhead_cost,
                COALESCE(SUM(incident_cost), 0) as incident_cost,
                COALESCE(SUM(net_cost), 0) as total_cost,
                COALESCE(SUM(utility_kwh), 0) as energy_kwh
            ')
            ->first();

        $downtimeMinutes = (int) DowntimeEvent::query()
            ->where('plant_id', $plantId)
            ->where('line_id', $lineId)
            ->whereDate('start_at', $date)
            ->sum('duration_minutes');

        $outputKg = (float) ($batchAgg->output_kg ?? 0);
        $inputKg = (float) ($batchAgg->input_kg ?? 0);
        $totalCost = (float) ($batchAgg->total_cost ?? 0);

        $yield = $inputKg > 0 ? ($outputKg / $inputKg) * 100 : 0.0;
        $costPerKg = $outputKg > 0 ? $totalCost / $outputKg : 0.0;

        FactDailyLineCost::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'plant_id' => $plantId,
                'line_id' => $lineId,
                'production_date' => $date,
            ],
            [
                'input_kg' => $inputKg,
                'output_kg' => $outputKg,
                'material_cost' => (float) ($batchAgg->material_cost ?? 0),
                'labor_cost' => (float) ($batchAgg->labor_cost ?? 0),
                'utility_cost' => (float) ($batchAgg->utility_cost ?? 0),
                'overhead_cost' => (float) ($batchAgg->overhead_cost ?? 0),
                'incident_cost' => (float) ($batchAgg->incident_cost ?? 0),
                'total_cost' => $totalCost,
                'cost_per_kg' => $costPerKg,
                'yield_percent' => $yield,
                'batch_count' => (int) ($batchAgg->batch_count ?? 0),
                'downtime_minutes' => $downtimeMinutes,
                'energy_kwh' => (float) ($batchAgg->energy_kwh ?? 0),
            ]
        );
    }

    public function updateDailyPlantKpi(int $tenantId, int $plantId, string $date): void
    {
        $batchAgg = ProductionBatch::query()
            ->where('plant_id', $plantId)
            ->whereDate('batch_date', $date)
            ->selectRaw('
                COUNT(*) as batch_count,
                COALESCE(SUM(input_kg), 0) as input_kg,
                COALESCE(SUM(good_output_kg), 0) as good_output_kg,
                COALESCE(SUM(net_cost), 0) as actual_cost,
                COALESCE(SUM(utility_kwh), 0) as energy_kwh
            ')
            ->first();

        $downtimeMinutes = (int) DowntimeEvent::query()
            ->where('plant_id', $plantId)
            ->whereDate('start_at', $date)
            ->sum('duration_minutes');

        $incidentAgg = CostIncident::query()
            ->where('plant_id', $plantId)
            ->whereDate('occurred_at', $date)
            ->selectRaw('
                COALESCE(SUM(cash_cost), 0) as incident_cash,
                COALESCE(SUM(opportunity_cost), 0) as incident_opp
            ')
            ->first();

        $openIncidents = CostIncident::query()
            ->where('plant_id', $plantId)
            ->where('status', '!=', 'closed')
            ->count();

        $inputKg = (float) ($batchAgg->input_kg ?? 0);
        $outputKg = (float) ($batchAgg->good_output_kg ?? 0);
        $actualCost = (float) ($batchAgg->actual_cost ?? 0);
        $energyKwh = (float) ($batchAgg->energy_kwh ?? 0);

        $yield = $inputKg > 0 ? ($outputKg / $inputKg) * 100 : 0.0;
        $costPerKg = $outputKg > 0 ? $actualCost / $outputKg : 0.0;
        $kwhPerKg = $outputKg > 0 ? $energyKwh / $outputKg : 0.0;

        // Baseline standard cost comparison
        $standardCost = $outputKg * 0.95; // default baseline standard approx $/kg
        $variance = $actualCost - $standardCost;

        FactDailyPlantKpi::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'plant_id' => $plantId,
                'production_date' => $date,
            ],
            [
                'input_kg' => $inputKg,
                'good_output_kg' => $outputKg,
                'yield_percent' => $yield,
                'actual_cost' => $actualCost,
                'standard_cost' => $standardCost,
                'variance_cost' => $variance,
                'cost_per_kg' => $costPerKg,
                'kwh_per_kg' => $kwhPerKg,
                'incident_cash_cost' => (float) ($incidentAgg->incident_cash ?? 0),
                'incident_opportunity_cost' => (float) ($incidentAgg->incident_opp ?? 0),
                'open_incident_count' => $openIncidents,
                'downtime_minutes' => $downtimeMinutes,
                'batch_count' => (int) ($batchAgg->batch_count ?? 0),
            ]
        );
    }

    public function updateMonthlyVariance(int $tenantId, int $plantId, string $month): void
    {
        $start = Carbon::parse($month)->startOfMonth()->toDateString();
        $end = Carbon::parse($month)->endOfMonth()->toDateString();

        $batchAgg = ProductionBatch::query()
            ->where('plant_id', $plantId)
            ->whereBetween('batch_date', [$start, $end])
            ->selectRaw('
                COALESCE(SUM(good_output_kg), 0) as output_kg,
                COALESCE(SUM(input_kg), 0) as input_kg,
                COALESCE(SUM(material_cost), 0) as material_cost,
                COALESCE(SUM(labor_cost), 0) as labor_cost,
                COALESCE(SUM(utility_cost), 0) as utility_cost,
                COALESCE(SUM(overhead_cost), 0) as overhead_cost,
                COALESCE(SUM(incident_cost), 0) as incident_cost,
                COALESCE(SUM(net_cost), 0) as actual_cost,
                COALESCE(SUM(utility_kwh), 0) as energy_kwh
            ')
            ->first();

        $outputKg = (float) ($batchAgg->output_kg ?? 0);
        $actualCost = (float) ($batchAgg->actual_cost ?? 0);
        $standardCost = $outputKg * 0.95;
        $totalVariance = $actualCost - $standardCost;

        FactMonthlyVariance::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'plant_id' => $plantId,
                'period_month' => $start,
            ],
            [
                'actual_cost' => $actualCost,
                'standard_cost' => $standardCost,
                'total_variance' => $totalVariance,
                'material_price_variance' => round($totalVariance * 0.40, 2),
                'material_usage_variance' => round($totalVariance * 0.25, 2),
                'labor_rate_variance' => round($totalVariance * 0.10, 2),
                'labor_efficiency_variance' => round($totalVariance * 0.10, 2),
                'utility_efficiency_variance' => round($totalVariance * 0.05, 2),
                'overhead_volume_variance' => round($totalVariance * 0.05, 2),
                'incident_variance' => (float) ($batchAgg->incident_cost ?? 0),
                'output_kg' => $outputKg,
            ]
        );
    }

    public function rebuild(?int $plantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $batchQuery = ProductionBatch::query();
        if ($plantId) {
            $batchQuery->where('plant_id', $plantId);
        }
        if ($startDate) {
            $batchQuery->whereDate('batch_date', '>=', $startDate);
        }
        if ($endDate) {
            $batchQuery->whereDate('batch_date', '<=', $endDate);
        }

        $processedBatches = 0;
        foreach ($batchQuery->cursor() as $batch) {
            $this->rollupBatch($batch);
            $processedBatches++;
        }

        $incidentQuery = CostIncident::query();
        if ($plantId) {
            $incidentQuery->where('plant_id', $plantId);
        }
        $processedIncidents = 0;
        foreach ($incidentQuery->cursor() as $incident) {
            $this->rollupIncident($incident);
            $processedIncidents++;
        }

        $downtimeQuery = DowntimeEvent::query();
        if ($plantId) {
            $downtimeQuery->where('plant_id', $plantId);
        }
        $processedDowntimes = 0;
        foreach ($downtimeQuery->cursor() as $downtime) {
            $this->rollupDowntime($downtime);
            $processedDowntimes++;
        }

        return [
            'batches_processed' => $processedBatches,
            'incidents_processed' => $processedIncidents,
            'downtimes_processed' => $processedDowntimes,
            'fact_daily_line_count' => FactDailyLineCost::count(),
            'fact_daily_plant_count' => FactDailyPlantKpi::count(),
            'fact_monthly_variance_count' => FactMonthlyVariance::count(),
            'fact_incident_summary_count' => FactIncidentSummary::count(),
        ];
    }
}
