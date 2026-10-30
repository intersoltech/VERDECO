<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostIncident>
 */
class CostIncidentFactory extends Factory
{
    protected $model = CostIncident::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attrs) => Plant::find($attrs['plant_id'])?->tenant_id ?? 1,
            'plant_id' => Plant::factory(),
            'line_id' => ProductionLine::factory(),
            'incident_type' => fake()->randomElement(['emergency_maintenance', 'material_loss', 'power_outage', 'extra_labor']),
            'severity' => fake()->randomElement(['minor', 'moderate', 'major', 'critical']),
            'root_cause_category' => fake()->randomElement(['Mechanical', 'Feedstock Contamination', 'Operator Error', 'Electrical Utility']),
            'description' => fake()->sentence(),
            'owner' => fake()->name(),
            'status' => 'open',
            'occurred_at' => now(),
            'cash_cost' => fake()->randomFloat(2, 200, 5000),
            'opportunity_cost' => fake()->randomFloat(2, 500, 10000),
            'allocation_rule' => 'by_kg',
        ];
    }
}
