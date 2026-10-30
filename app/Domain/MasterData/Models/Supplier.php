<?php

namespace App\Domain\MasterData\Models;

use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected static function newFactory(): \Database\Factories\SupplierFactory
    {
        return \Database\Factories\SupplierFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'name',
        'code',
        'country_code',
        'is_active',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'supplier_id');
    }
}
