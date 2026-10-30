<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plant>
 */
class PlantFactory extends Factory
{
    protected $model = Plant::class;

    public function definition(): array
    {
        $city = fake()->city();

        return [
            'tenant_id' => Organization::factory(),
            'name' => "Verdeco {$city}",
            'code' => 'V' . strtoupper(fake()->unique()->lexify('???')),
            'location' => "{$city}, " . fake()->stateAbbr(),
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
            'costing_mode' => 'both',
            'is_active' => true,
        ];
    }
}
