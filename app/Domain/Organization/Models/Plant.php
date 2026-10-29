<?php

namespace App\Domain\Organization\Models;

use App\Domain\Costing\Models\CostCategory;
use App\Domain\Costing\Models\StandardCost;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\MasterData\Models\Material;
use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\Production\Models\ProductionLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plant extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'location',
        'timezone',
        'base_currency',
        'costing_mode',
        'is_active',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'tenant_id');
    }

    public function productionLines(): HasMany
    {
        return $this->hasMany(ProductionLine::class, 'plant_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'plant_id');
    }

    public function productGrades(): HasMany
    {
        return $this->hasMany(ProductGrade::class, 'plant_id');
    }

    public function costCategories(): HasMany
    {
        return $this->hasMany(CostCategory::class, 'plant_id');
    }

    public function standardCosts(): HasMany
    {
        return $this->hasMany(StandardCost::class, 'plant_id');
    }

    public function costIncidents(): HasMany
    {
        return $this->hasMany(CostIncident::class, 'plant_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('auth.providers.users.model', \App\Models\User::class), 'plant_user', 'plant_id', 'user_id');
    }
}
