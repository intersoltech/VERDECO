<?php

namespace App\Domain\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialPrice extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'material_id',
        'currency_code',
        'unit_price',
        'effective_from',
        'effective_to',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
