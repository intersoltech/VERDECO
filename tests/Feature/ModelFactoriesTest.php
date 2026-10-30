<?php

declare(strict_types=1);

use App\Domain\Costing\Models\StandardCost;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\MasterData\Models\Material;
use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\MasterData\Models\Supplier;
use App\Domain\MasterData\Models\Unit;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\DowntimeEvent;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchMaterial;
use App\Domain\Production\Models\ProductionLine;
use App\Domain\Quality\Models\BatchQualityResult;

it('creates all domain entities using model factories', function (): void {
    $org = Organization::factory()->create();
    expect($org)->toBeInstanceOf(Organization::class);

    $plant = Plant::factory()->create(['tenant_id' => $org->id]);
    expect($plant)->toBeInstanceOf(Plant::class);

    $line = ProductionLine::factory()->create(['tenant_id' => $org->id, 'plant_id' => $plant->id]);
    expect($line)->toBeInstanceOf(ProductionLine::class);

    $supplier = Supplier::factory()->create(['tenant_id' => $org->id, 'plant_id' => $plant->id]);
    expect($supplier)->toBeInstanceOf(Supplier::class);

    $material = Material::factory()->create(['tenant_id' => $org->id, 'plant_id' => $plant->id, 'supplier_id' => $supplier->id]);
    expect($material)->toBeInstanceOf(Material::class);

    $grade = ProductGrade::factory()->create(['tenant_id' => $org->id, 'plant_id' => $plant->id]);
    expect($grade)->toBeInstanceOf(ProductGrade::class);

    $standard = StandardCost::factory()->create([
        'tenant_id' => $org->id,
        'plant_id' => $plant->id,
        'line_id' => $line->id,
        'product_grade_id' => $grade->id,
    ]);
    expect($standard)->toBeInstanceOf(StandardCost::class);

    $batch = ProductionBatch::factory()->create([
        'tenant_id' => $org->id,
        'plant_id' => $plant->id,
        'line_id' => $line->id,
        'product_grade_id' => $grade->id,
    ]);
    expect($batch)->toBeInstanceOf(ProductionBatch::class);

    $batchMaterial = ProductionBatchMaterial::factory()->create([
        'tenant_id' => $org->id,
        'plant_id' => $plant->id,
        'production_batch_id' => $batch->id,
        'material_id' => $material->id,
    ]);
    expect($batchMaterial)->toBeInstanceOf(ProductionBatchMaterial::class);

    $incident = CostIncident::factory()->create([
        'tenant_id' => $org->id,
        'plant_id' => $plant->id,
        'line_id' => $line->id,
    ]);
    expect($incident)->toBeInstanceOf(CostIncident::class);

    $downtime = DowntimeEvent::factory()->create([
        'tenant_id' => $org->id,
        'plant_id' => $plant->id,
        'line_id' => $line->id,
    ]);
    expect($downtime)->toBeInstanceOf(DowntimeEvent::class);

    $quality = BatchQualityResult::factory()->create([
        'tenant_id' => $org->id,
        'plant_id' => $plant->id,
        'production_batch_id' => $batch->id,
    ]);
    expect($quality)->toBeInstanceOf(BatchQualityResult::class);

    $unit = Unit::factory()->create();
    expect($unit)->toBeInstanceOf(Unit::class);
});
