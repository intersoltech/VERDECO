<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Costing\Models\Budget;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\DowntimeEvent;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Quality\Models\BatchQualityResult;
use App\Domain\Reporting\Models\FactDailyPlantKpi;
use App\Domain\Reporting\Models\FactMonthlyVariance;
use Carbon\Carbon;

final class AlertRuleEngine
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function evaluate(): array
    {
        $alerts = [];

        foreach (Plant::query()->where('is_active', true)->get() as $plant) {
            $rules = AlertRule::query()
                ->where('enabled', true)
                ->where(function ($query) use ($plant) {
                    $query->whereNull('plant_id')->orWhere('plant_id', $plant->id);
                })
                ->get();

            foreach ($rules as $rule) {
                $currentValue = $this->extractValueForRule($plant, $rule->code);
                if ($currentValue === null) {
                    continue;
                }

                $matches = $this->matchesThreshold($currentValue, $rule->threshold_value, $rule->comparison_operator);
                if (! $matches) {
                    continue;
                }

                $alerts[] = [
                    'plant_id' => $plant->id,
                    'rule' => $rule->code,
                    'severity' => $rule->severity,
                    'message' => $this->renderMessage($rule, $plant, $currentValue),
                ];
            }
        }

        return $alerts;
    }

    private function extractValueForRule(Plant $plant, string $ruleCode): ?float
    {
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();

        return match ($ruleCode) {
            // 1. Cost Drift: cost/kg > standard by X%
            'cost_per_kg_drift' => $this->calculateCostDriftPercent($plant, $startOfMonth),

            // 2. Yield below threshold: yield % < threshold
            'yield_below_threshold' => $this->calculateYieldPercent($plant, $startOfMonth),

            // 3. Overtime hours > threshold
            'overtime_hours_threshold' => (float) ProductionBatch::query()
                ->where('plant_id', $plant->id)
                ->whereDate('batch_date', '>=', $startOfMonth)
                ->sum('labor_overtime_hours'),

            // 4. Unplanned downtime > threshold (minutes)
            'unplanned_downtime_threshold' => (float) DowntimeEvent::query()
                ->where('plant_id', $plant->id)
                ->where('downtime_type', 'unplanned')
                ->whereDate('start_at', '>=', $startOfMonth)
                ->sum('duration_minutes'),

            // 5. Energy intensity spike: kWh/kg above baseline
            'energy_intensity_spike' => $this->calculateEnergyIntensity($plant, $startOfMonth),

            // 6. Quality hold / off-spec batches
            'quality_hold_batch' => (float) BatchQualityResult::query()
                ->where('plant_id', $plant->id)
                ->whereDate('tested_at', '>=', $startOfMonth)
                ->whereIn('status', ['hold', 'fail'])
                ->count(),

            // 7. Incident cost > threshold
            'incident_cost_threshold' => (float) $plant->costIncidents()
                ->whereNotIn('status', ['closed', 'resolved'])
                ->sum('cash_cost'),

            // 8. Budget category / plant budget consumed > 90%
            'budget_consumed_threshold' => $this->calculateBudgetConsumptionPercent($plant, $startOfMonth),

            // Legacy operational rule
            'daily_costing_alert' => (float) $plant->costIncidents()
                ->whereNotIn('status', ['closed', 'resolved'])
                ->count(),

            default => null,
        };
    }

    private function calculateCostDriftPercent(Plant $plant, string $startOfMonth): float
    {
        $variance = FactMonthlyVariance::query()
            ->where('plant_id', $plant->id)
            ->where('period_month', $startOfMonth)
            ->first();

        if ($variance && (float) $variance->standard_cost > 0) {
            $act = (float) $variance->actual_cost;
            $std = (float) $variance->standard_cost;

            return max(0.0, (($act - $std) / $std) * 100);
        }

        $latestKpi = FactDailyPlantKpi::query()
            ->where('plant_id', $plant->id)
            ->latest('production_date')
            ->first();

        if ($latestKpi && (float) $latestKpi->standard_cost > 0) {
            $act = (float) $latestKpi->actual_cost;
            $std = (float) $latestKpi->standard_cost;

            return max(0.0, (($act - $std) / $std) * 100);
        }

        return 0.0;
    }

    private function calculateYieldPercent(Plant $plant, string $startOfMonth): float
    {
        $kpis = FactDailyPlantKpi::query()
            ->where('plant_id', $plant->id)
            ->whereDate('production_date', '>=', $startOfMonth);

        $in = (float) (clone $kpis)->sum('input_kg');
        $out = (float) (clone $kpis)->sum('good_output_kg');

        if ($in > 0) {
            return ($out / $in) * 100;
        }

        $latestYield = FactDailyPlantKpi::query()
            ->where('plant_id', $plant->id)
            ->latest('production_date')
            ->value('yield_percent');

        return $latestYield !== null ? (float) $latestYield : 92.5;
    }

    private function calculateEnergyIntensity(Plant $plant, string $startOfMonth): float
    {
        $latestKpi = FactDailyPlantKpi::query()
            ->where('plant_id', $plant->id)
            ->whereDate('production_date', '>=', $startOfMonth)
            ->where('kwh_per_kg', '>', 0)
            ->latest('production_date')
            ->first();

        return $latestKpi ? (float) $latestKpi->kwh_per_kg : 0.38;
    }

    private function calculateBudgetConsumptionPercent(Plant $plant, string $startOfMonth): float
    {
        $actualCost = (float) FactMonthlyVariance::query()
            ->where('plant_id', $plant->id)
            ->where('period_month', $startOfMonth)
            ->value('actual_cost');

        $budgetCost = (float) Budget::query()
            ->where('plant_id', $plant->id)
            ->where('period_month', $startOfMonth)
            ->value('budgeted_cost');

        if ($budgetCost > 0) {
            return ($actualCost / $budgetCost) * 100;
        }

        return 0.0;
    }

    private function matchesThreshold(float $value, mixed $threshold, string $operator): bool
    {
        $thresholdValue = (float) $threshold;

        return match ($operator) {
            'gt' => $value > $thresholdValue,
            'gte' => $value >= $thresholdValue,
            'lt' => $value < $thresholdValue,
            'lte' => $value <= $thresholdValue,
            'eq' => $value == $thresholdValue,
            default => false,
        };
    }

    private function renderMessage(AlertRule $rule, Plant $plant, float $value): string
    {
        $template = $rule->message_template ?: sprintf('%s exceeded threshold for plant %s.', $rule->name, $plant->code);

        return str_replace([
            '{plant_code}',
            '{rule_name}',
            '{threshold}',
            '{value}',
        ], [
            $plant->code,
            $rule->name,
            (string) $rule->threshold_value,
            number_format($value, 2),
        ], $template);
    }
}
