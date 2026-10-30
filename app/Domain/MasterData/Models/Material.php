<?php

namespace App\Domain\MasterData\Models;

use App\Domain\Organization\Models\Plant;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    protected static function newFactory(): MaterialFactory
    {
        return MaterialFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'supplier_id',
        'name',
        'code',
        'category',
        'unit',
        'is_active',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(MaterialPrice::class, 'material_id');
    }
}
