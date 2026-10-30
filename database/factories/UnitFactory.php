<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\MasterData\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'code' => 'kg-' . fake()->unique()->numerify('###'),
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass',
            'conversion_to_base' => 1.00000000,
            'is_active' => true,
        ];
    }
}
