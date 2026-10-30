<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductGrade>
 */
class ProductGradeFactory extends Factory
{
    protected $model = ProductGrade::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attrs) => Plant::find($attrs['plant_id'])?->tenant_id ?? 1,
            'plant_id' => Plant::factory(),
            'name' => 'Food Grade rPET ' . fake()->randomElement(['Bottle-to-Bottle', 'Sheet Grade', 'Thermoforming']),
            'code' => 'GRADE-' . strtoupper(fake()->unique()->bothify('???-###')),
            'grade_type' => 'food_grade',
            'iv_min' => 0.8000,
            'iv_max' => 0.8400,
            'aa_max' => 1.0000,
            'color_l_min' => 65.0000,
            'color_l_max' => 75.0000,
            'color_b_min' => -1.0000,
            'color_b_max' => 2.0000,
            'is_active' => true,
        ];
    }
}
