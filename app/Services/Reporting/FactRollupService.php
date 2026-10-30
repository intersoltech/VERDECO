<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Domain\Costing\Models\StandardCost;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Production\Models\DowntimeEvent;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Reporting\Models\FactDailyLineCost;
use App\Domain\Reporting\Models\FactDailyPlantKpi;
use App\Domain\Reporting\Models\FactIncidentSummary;
use App\Domain\Reporting\Models\FactMonthlyVariance;
use App\Services\Costing\VarianceAnalyzer;
use Carbon\Carbon;

final class FactRollupService
{
    public function __construct(
        private readonly VarianceAnalyzer $varianceAnalyzer = new VarianceAnalyzer
    ) {}

    public function rollupBatch(ProductionBatch $batch): void
    {
        if (! $batch->plant_id || ! $batch->batch_date) {
            return;
        }

        $date = Carbon::parse($batch->batch_date)->toDateString();
        $month = Carbon::parse($batch->batch_date)->startOfMonth()->toDateString();
        $tenantId = (int) ($batch->tenant_id ?? 1);
        $plantId = (int) $batch->plant_id;

        if ($batch->line_id) {
            $this->updateDailyLineCost($tenantId, $plantId, (int) $batch->line_id, $date);
        }

        $this->updateDailyPlantKpi($tenantId, $plantId, $date);
        $this->updateMonthlyVariance($tenantId, $plantId, $month);
    }

    public function rollupIncident(CostIncident $incident): void
    {
        if (! $incident->plant_id || ! $incident->occurred_at) {
            return;
        }

        $date = Carbon::parse($incident->occurred_at)->toDateString();
        $tenantId = (int) ($incident->tenant_id ?? 1);
        $plantId = (int) $incident->plant_id;

        $cashCost = (float) CostIncident::query()
            ->where('plant_id', $plantId)
            ->whereDate('occurred_at', $date)
            ->where('incident_type', $incident->incident_type)
            ->where('root_cause_category', $incident->root_cause_category)
            ->sum('cash_cost');

        $opportunityCost = (float) CostIncident::query()
            ->where('plant_id', $plantId)
            ->whereDate('occurred_at', $date)
            ->where('incident_type', $incident->incident_type)
            ->where('root_cause_category', $incident->root_cause_category)
            ->sum('opportunity_cost');

        $incidentCount = CostIncident::query()
            ->where('plant_id', $plantId)
            ->whereDate('occurred_at', $date)
            ->where('incident_type', $incident->incident_type)
            ->where('root_cause_category', $incident->root_cause_category)
            ->count();

        FactIncidentSummary::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'plant_id' => $plantId,
                'incident_date' => $date,
                'incident_type' => $incident->incident_type,
                'root_cause_category' => $incident->root_cause_category,
            ],
            [
                'cash_cost' => round($cashCost, 2),
                'opportunity_cost' => round($opportunityCost, 2),
                'incident_count' => $incidentCount,
            ]
        );

        $this->updateDailyPlantKpi($tenantId, $plantId, $date);
    }

    public function rollupDowntime(DowntimeEvent $downtime): void
    {
        if (! $downtime->plant_id || ! $downtime->start_at) {
            return;
        }

        $date = Carbon::parse($downtime->start_at)->toDateString();
        $tenantId = (int) ($downtime->tenant_id ?? 1);
        $plantId = (int) $downtime->plant_id;

        if ($downtime->line_id) {
            $this->updateDailyLineCost($tenantId, $plantId, (int) $downtime->line_id, $date);
        }

        $this->updateDailyPlantKpi($tenantId, $plantId, $date);
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
                'input_kg' => round($inputKg, 4),
                'output_kg' => round($outputKg, 4),
                'material_cost' => round((float) ($batchAgg->material_cost ?? 0), 2),
                'labor_cost' => round((float) ($batchAgg->labor_cost ?? 0), 2),
                'utility_cost' => round((float) ($batchAgg->utility_cost ?? 0), 2),
                'overhead_cost' => round((float) ($batchAgg->overhead_cost ?? 0), 2),
                'incident_cost' => round((float) ($batchAgg->incident_cost ?? 0), 2),
                'total_cost' => round($totalCost, 2),
                'cost_per_kg' => round($costPerKg, 6),
                'yield_percent' => round($yield, 4),
                'batch_count' => (int) ($batchAgg->batch_count ?? 0),
                'downtime_minutes' => $downtimeMinutes,
                'energy_kwh' => round((float) ($batchAgg->energy_kwh ?? 0), 4),
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

        // Resolve standard cost as of this production date
        $standard = StandardCost::query()
            ->where('plant_id', $plantId)
            ->where('status', 'approved')
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->latest('effective_from')
            ->first();

        $stdKgCost = 0.95;
        if ($standard) {
            $stdYield = ((float) $standard->standard_yield_percent) / 100.0;
            $matCostPerKg = $stdYield > 0 ? (0.68 / $stdYield) : 0.68;
            $laborCostPerKg = (((float) $standard->labor_hours_per_ton) / 1000.0) * 32.0;
            $utilCostPerKg = (float) $standard->kwh_per_kg * 0.12;
            $overheadCostPerKg = (float) $standard->overhead_rate;
            $stdKgCost = $matCostPerKg + $laborCostPerKg + $utilCostPerKg + $overheadCostPerKg;
        }

        $standardCost = $outputKg * $stdKgCost;
        $variance = $actualCost - $standardCost;

        FactDailyPlantKpi::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'plant_id' => $plantId,
                'production_date' => $date,
            ],
            [
                'input_kg' => round($inputKg, 4),
                'good_output_kg' => round($outputKg, 4),
                'yield_percent' => round($yield, 4),
                'actual_cost' => round($actualCost, 2),
                'standard_cost' => round($standardCost, 2),
                'variance_cost' => round($variance, 2),
                'cost_per_kg' => round($costPerKg, 6),
                'kwh_per_kg' => round($kwhPerKg, 6),
                'incident_cash_cost' => round((float) ($incidentAgg->incident_cash ?? 0), 2),
                'incident_opportunity_cost' => round((float) ($incidentAgg->incident_opp ?? 0), 2),
                'open_incident_count' => $openIncidents,
                'downtime_minutes' => $downtimeMinutes,
                'batch_count' => (int) ($batchAgg->batch_count ?? 0),
            ]
        );

        app(DashboardCacheService::class)->invalidatePlant($plantId);
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
                COALESCE(SUM(utility_kwh), 0) as energy_kwh,
                COALESCE(SUM(labor_regular_hours + labor_overtime_hours), 0) as labor_hours
            ')
            ->first();

        $outputKg = (float) ($batchAgg->output_kg ?? 0);
        $inputKg = (float) ($batchAgg->input_kg ?? 0);
        $actualCost = (float) ($batchAgg->actual_cost ?? 0);
        $energyKwh = (float) ($batchAgg->energy_kwh ?? 0);
        $laborHours = (float) ($batchAgg->labor_hours ?? 0);

        // Fetch standard cost for the plant and month
        $standard = StandardCost::query()
            ->where('plant_id', $plantId)
            ->where('status', 'approved')
            ->where('effective_from', '<=', $start)
            ->where(function ($q) use ($start) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start);
            })
            ->latest('effective_from')
            ->first();

        $stdYield = $standard ? (((float) $standard->standard_yield_percent) / 100.0) : 0.925;
        $stdKwhPerKg = $standard ? (float) $standard->kwh_per_kg : 0.38;
        $stdHoursPerTon = $standard ? (float) $standard->labor_hours_per_ton : 3.2;
        $stdOverheadRate = $standard ? (float) $standard->overhead_rate : 0.16;

        $expectedMaterialKg = $stdYield > 0 ? ($outputKg / $stdYield) : $inputKg;
        $stdMaterialUnitPrice = 0.68;
        $stdLaborRate = 32.0;
        $stdUtilityRate = 0.12;

        $stdMaterialCost = $expectedMaterialKg * $stdMaterialUnitPrice;
        $stdLaborCost = ($outputKg / 1000.0) * $stdHoursPerTon * $stdLaborRate;
        $stdUtilityCost = $outputKg * $stdKwhPerKg * $stdUtilityRate;
        $stdOverheadCost = $outputKg * $stdOverheadRate;
        $standardCostTotal = $stdMaterialCost + $stdLaborCost + $stdUtilityCost + $stdOverheadCost;

        $actualMaterialCost = (float) ($batchAgg->material_cost ?? 0);
        $actualLaborCost = (float) ($batchAgg->labor_cost ?? 0);
        $actualUtilityCost = (float) ($batchAgg->utility_cost ?? 0);
        $actualOverheadCost = (float) ($batchAgg->overhead_cost ?? 0);
        $actualIncidentCost = (float) ($batchAgg->incident_cost ?? 0);

        // Compute individual variances
        $actualMaterialRate = $inputKg > 0 ? ($actualMaterialCost / $inputKg) : $stdMaterialUnitPrice;
        $matPriceVar = ($actualMaterialRate - $stdMaterialUnitPrice) * $inputKg;
        $matUsageVar = ($inputKg - $expectedMaterialKg) * $stdMaterialUnitPrice;

        $actualLaborRate = $laborHours > 0 ? ($actualLaborCost / $laborHours) : $stdLaborRate;
        $expectedLaborHours = ($outputKg / 1000.0) * $stdHoursPerTon;
        $laborRateVar = ($actualLaborRate - $stdLaborRate) * $laborHours;
        $laborEffVar = ($laborHours - $expectedLaborHours) * $stdLaborRate;

        $actualKwhPerKg = $outputKg > 0 ? ($energyKwh / $outputKg) : $stdKwhPerKg;
        $utilEffVar = ($actualKwhPerKg - $stdKwhPerKg) * $outputKg * $stdUtilityRate;
        $overheadVolumeVar = $actualOverheadCost - $stdOverheadCost;
        $incidentVar = $actualIncidentCost;

        $totalVariance = $actualCost - $standardCostTotal;

        FactMonthlyVariance::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'plant_id' => $plantId,
                'period_month' => $start,
            ],
            [
                'actual_cost' => round($actualCost, 2),
                'standard_cost' => round($standardCostTotal, 2),
                'total_variance' => round($totalVariance, 2),
                'material_price_variance' => round($matPriceVar, 2),
                'material_usage_variance' => round($matUsageVar, 2),
                'labor_rate_variance' => round($laborRateVar, 2),
                'labor_efficiency_variance' => round($laborEffVar, 2),
                'utility_efficiency_variance' => round($utilEffVar, 2),
                'overhead_volume_variance' => round($overheadVolumeVar, 2),
                'incident_variance' => round($incidentVar, 2),
                'output_kg' => round($outputKg, 4),
            ]
        );
    }

    /**
     * @return array<string, int>
     */
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
        if ($startDate) {
            $incidentQuery->whereDate('occurred_at', '>=', $startDate);
        }
        if ($endDate) {
            $incidentQuery->whereDate('occurred_at', '<=', $endDate);
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
        if ($startDate) {
            $downtimeQuery->whereDate('start_at', '>=', $startDate);
        }
        if ($endDate) {
            $downtimeQuery->whereDate('start_at', '<=', $endDate);
        }
        $processedDowntimes = 0;
        foreach ($downtimeQuery->cursor() as $downtime) {
            $this->rollupDowntime($downtime);
            $processedDowntimes++;
        }

        app(DashboardCacheService::class)->invalidateAll();

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
