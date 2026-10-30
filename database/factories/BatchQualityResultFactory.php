<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Quality\Models\BatchQualityResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatchQualityResult>
 */
class BatchQualityResultFactory extends Factory
{
    protected $model = BatchQualityResult::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attrs) => ProductionBatch::find($attrs['production_batch_id'])?->tenant_id ?? 1,
            'plant_id' => fn (array $attrs) => ProductionBatch::find($attrs['production_batch_id'])?->plant_id ?? 1,
            'production_batch_id' => ProductionBatch::factory(),
            'tested_at' => now(),
            'sample_identifier' => 'SMP-' . fake()->unique()->numerify('#####'),
            'intrinsic_viscosity' => 0.8250,
            'acetaldehyde_ppm' => 0.8500,
            'color_l' => 72.5000,
            'color_a' => -0.5000,
            'color_b' => 0.8000,
            'moisture_ppm' => 40.0000,
            'black_specks_count' => 2,
            'fines_percent' => 0.0500,
            'status' => 'pass',
            'hold_reason' => null,
            'inspector_notes' => 'Within target specs',
            'inspected_by' => null,
        ];
    }
}
