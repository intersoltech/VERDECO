<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\DowntimeEvent;
use App\Domain\Production\Models\ProductionLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DowntimeEvent>
 */
class DowntimeEventFactory extends Factory
{
    protected $model = DowntimeEvent::class;

    public function definition(): array
    {
        $start = now()->subHours(4);
        $end = now()->subHours(2);

        return [
            'tenant_id' => fn (array $attrs) => Plant::find($attrs['plant_id'])?->tenant_id ?? 1,
            'plant_id' => Plant::factory(),
            'line_id' => ProductionLine::factory(),
            'start_at' => $start,
            'end_at' => $end,
            'duration_minutes' => 120,
            'downtime_type' => fake()->randomElement(['planned', 'unplanned']),
            'reason_code' => fake()->randomElement(['MECH-BRK', 'CHG-OVR', 'SCREEN-CLN', 'PWR-DIP']),
            'description' => fake()->sentence(),
            'lost_production_kg' => 450.0000,
        ];
    }
}
