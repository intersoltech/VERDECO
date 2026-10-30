<?php

namespace App\Domain\Production\Models;

use App\Domain\MasterData\Models\Material;
use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionBatchMaterial extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected static function newFactory(): \Database\Factories\ProductionBatchMaterialFactory
    {
        return \Database\Factories\ProductionBatchMaterialFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'production_batch_id',
        'material_id',
        'material_name',
        'kg',
        'unit_price',
    ];

    protected $casts = [
        'kg' => 'decimal:4',
        'unit_price' => 'decimal:6',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductionBatchMaterial $material): void {
            $batch = $material->batch;
            $material->tenant_id = $batch->tenant_id;
            $material->plant_id = $batch->plant_id;
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Production\Models\ProductionBatch::class, 'production_batch_id');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}