<?php

namespace App\Domain\Production\Models;

use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLine extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected static function newFactory(): \Database\Factories\ProductionLineFactory
    {
        return \Database\Factories\ProductionLineFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'name',
        'code',
        'type',
        'rated_kg_per_hr',
        'rated_kw',
        'is_active',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
