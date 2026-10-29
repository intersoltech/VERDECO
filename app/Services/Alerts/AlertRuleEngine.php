<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Organization\Models\Plant;

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
                if (!$matches) {
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
        return match ($ruleCode) {
            'incident_cost_threshold' => (float) $plant->costIncidents()->whereNotIn('status', ['closed', 'resolved'])->sum('cash_cost'),
            'daily_costing_alert' => $this->calculateOpenIncidentCount($plant),
            default => null,
        };
    }

    private function calculateOpenIncidentCount(Plant $plant): float
    {
        return (float) $plant->costIncidents()->whereNotIn('status', ['closed', 'resolved'])->count();
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
            (string) number_format($value, 2),
        ], $template);
    }
}
