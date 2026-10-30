<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionLine>
 */
class ProductionLineFactory extends Factory
{
    protected $model = ProductionLine::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attrs) => Plant::find($attrs['plant_id'])?->tenant_id ?? 1,
            'plant_id' => Plant::factory(),
            'name' => 'Extrusion Line '.fake()->numberBetween(1, 10),
            'code' => 'LINE-'.strtoupper(fake()->unique()->bothify('??-##')),
            'type' => 'extrusion',
            'rated_kg_per_hr' => 2000.0000,
            'rated_kw' => 450.0000,
            'is_active' => true,
        ];
    }
}
