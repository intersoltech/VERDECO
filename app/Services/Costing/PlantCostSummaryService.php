<?php

declare(strict_types=1);

namespace App\Services\Costing;

use App\Domain\Organization\Models\Plant;
use App\Models\User;

final class PlantCostSummaryService
{
    /**
     * @return array<string, float|int|string>
     */
    public function forPlant(Plant $plant): array
    {
        $openIncidents = $plant->costIncidents()->where('status', '!=', 'closed')->get();
        $incidentTotal = (float) $openIncidents->sum('cash_cost');
        $incidentCount = $openIncidents->count();
        $baseline = match ($plant->costing_mode) {
            'batch' => 1200.0,
            'daily' => 900.0,
            'both' => 1500.0,
            default => 1000.0,
        };

        $variance = max(0.0, $incidentTotal - $baseline);
        $costPerIncident = $incidentCount > 0 ? $incidentTotal / $incidentCount : 0.0;

        return [
            'plant_id' => $plant->id,
            'plant_code' => $plant->code,
            'plant_name' => $plant->name,
            'costing_mode' => $plant->costing_mode,
            'open_incident_count' => $incidentCount,
            'open_incident_cost' => round($incidentTotal, 2),
            'baseline_cost' => round($baseline, 2),
            'variance_to_baseline' => round($variance, 2),
            'cost_per_incident' => round($costPerIncident, 2),
        ];
    }

    /**
     * @return array<int, array<string, float|int|string>>
     */
    public function forOrganization(?User $user = null): array
    {
        $summaries = [];
        $plants = Plant::query()->with('costIncidents');

        if ($user !== null && ! $user->hasRole('admin')) {
            $plants->whereKey($user->accessiblePlantIds());
        }

        foreach ($plants->get() as $plant) {
            $summaries[] = $this->forPlant($plant);
        }

        return $summaries;
    }
}
