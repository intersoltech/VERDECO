<?php

namespace App\Domain\Costing\Models;

use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandardCost extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'line_id',
        'product_grade_id',
        'effective_from',
        'effective_to',
        'standard_yield_percent',
        'kg_per_hour',
        'kwh_per_kg',
        'labor_hours_per_ton',
        'material_usage_kg_per_kg',
        'overhead_rate',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
