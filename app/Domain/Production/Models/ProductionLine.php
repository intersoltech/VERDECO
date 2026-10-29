<?php

namespace App\Domain\Production\Models;

use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLine extends Model
{
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
