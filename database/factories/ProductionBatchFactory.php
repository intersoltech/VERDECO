<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionBatch>
 */
class ProductionBatchFactory extends Factory
{
    protected $model = ProductionBatch::class;

    public function definition(): array
    {
        $inputKg = 10000.0000;
        $goodOutputKg = 9400.0000;
        $matCost = 7000.00;
        $laborCost = 850.00;
        $utilityCost = 532.00;
        $overheadCost = 1128.00;
        $netCost = 9510.00;

        return [
            'tenant_id' => fn (array $attrs) => Plant::find($attrs['plant_id'])?->tenant_id ?? 1,
            'plant_id' => Plant::factory(),
            'line_id' => ProductionLine::factory(),
            'product_grade_id' => ProductGrade::factory(),
            'batch_number' => 'B-' . strtoupper(fake()->unique()->bothify('????-#####')),
            'batch_date' => now()->toDateString(),
            'status' => 'draft',
            'input_kg' => $inputKg,
            'good_output_kg' => $goodOutputKg,
            'recovery_credit' => 150.00,
            'labor_regular_rate' => 35.000000,
            'labor_overtime_rate' => 52.500000,
            'labor_regular_hours' => 20.0000,
            'labor_overtime_hours' => 2.0000,
            'labor_overtime_multiplier' => 1.5000,
            'utility_kwh' => 3800.0000,
            'utility_rate' => 0.140000,
            'overhead_pool' => $overheadCost,
            'overhead_basis' => 'per_kg',
            'incident_cost' => 0.00,
            'incident_allocation_rule' => 'direct_tag',
            'material_cost' => $matCost,
            'labor_cost' => $laborCost,
            'utility_cost' => $utilityCost,
            'overhead_cost' => $overheadCost,
            'total_cost' => $matCost + $laborCost + $utilityCost + $overheadCost,
            'net_cost' => $netCost,
            'yield_percent' => 94.0000,
            'cost_per_kg' => round($netCost / $goodOutputKg, 6),
            'calculated_at' => now(),
        ];
    }

    public function costed(): static
    {
        return $this->state(fn () => [
            'status' => 'costed',
        ]);
    }
}
