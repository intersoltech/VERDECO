<?php

namespace App\Domain\Costing\Models;

use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostCategory extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'name',
        'code',
        'kind',
        'type',
        'allocation_basis',
        'is_active',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
