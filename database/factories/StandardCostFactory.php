<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Costing\Models\StandardCost;
use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StandardCost>
 */
class StandardCostFactory extends Factory
{
    protected $model = StandardCost::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attrs) => Plant::find($attrs['plant_id'])?->tenant_id ?? 1,
            'plant_id' => Plant::factory(),
            'line_id' => ProductionLine::factory(),
            'product_grade_id' => ProductGrade::factory(),
            'effective_from' => now()->startOfYear(),
            'effective_to' => null,
            'standard_yield_percent' => 94.5000,
            'kg_per_hour' => 1850.0000,
            'kwh_per_kg' => 0.380000,
            'labor_hours_per_ton' => 3.200000,
            'material_usage_kg_per_kg' => 1.058200,
            'overhead_rate' => 0.120000,
        ];
    }
}
