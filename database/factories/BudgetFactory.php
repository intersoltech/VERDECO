<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Costing\Models\Budget;
use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attrs) => Plant::find($attrs['plant_id'])?->tenant_id ?? 1,
            'plant_id' => Plant::factory(),
            'cost_category_id' => 1,
            'period_month' => now()->startOfMonth()->toDateString(),
            'budgeted_cost' => 50000.00,
            'budgeted_output_kg' => 45000.0000,
            'budgeted_cost_per_kg' => 1.111111,
            'notes' => fake()->sentence(),
        ];
    }
}
