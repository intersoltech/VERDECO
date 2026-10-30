<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\MasterData\Models\Material;
use App\Domain\MasterData\Models\Supplier;
use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attrs) => Plant::find($attrs['plant_id'])?->tenant_id ?? 1,
            'plant_id' => Plant::factory(),
            'supplier_id' => Supplier::factory(),
            'name' => 'Clear Hot-Washed PET Flake Grade '.fake()->randomElement(['A', 'B', 'Super-Clean']),
            'code' => 'MAT-'.strtoupper(fake()->unique()->bothify('???-###')),
            'category' => 'feedstock',
            'unit' => 'kg',
            'is_active' => true,
        ];
    }
}
