<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\MasterData\Models\Supplier;
use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attrs) => Plant::find($attrs['plant_id'])?->tenant_id ?? 1,
            'plant_id' => Plant::factory(),
            'name' => fake()->company() . ' Recycling',
            'code' => 'SUP-' . strtoupper(fake()->unique()->bothify('???-###')),
            'country_code' => 'US',
            'is_active' => true,
        ];
    }
}
