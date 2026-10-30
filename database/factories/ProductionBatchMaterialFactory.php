<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\MasterData\Models\Material;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionBatchMaterial>
 */
class ProductionBatchMaterialFactory extends Factory
{
    protected $model = ProductionBatchMaterial::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attrs) => ProductionBatch::find($attrs['production_batch_id'])?->tenant_id ?? 1,
            'plant_id' => fn (array $attrs) => ProductionBatch::find($attrs['production_batch_id'])?->plant_id ?? 1,
            'production_batch_id' => ProductionBatch::factory(),
            'material_id' => Material::factory(),
            'material_name' => 'Clear Post-Consumer PET Flake',
            'kg' => 5000.0000,
            'unit_price' => 0.700000,
        ];
    }
}
