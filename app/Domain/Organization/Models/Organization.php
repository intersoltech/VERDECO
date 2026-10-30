<?php

namespace App\Domain\Organization\Models;

use App\Domain\Costing\Models\CostCategory;
use App\Domain\Costing\Models\StandardCost;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\MasterData\Models\Material;
use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\MasterData\Models\Supplier;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'country_code',
    ];

    public function plants(): HasMany
    {
        return $this->hasMany(Plant::class, 'tenant_id');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class, 'tenant_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'tenant_id');
    }

    public function productGrades(): HasMany
    {
        return $this->hasMany(ProductGrade::class, 'tenant_id');
    }

    public function costCategories(): HasMany
    {
        return $this->hasMany(CostCategory::class, 'tenant_id');
    }

    public function standardCosts(): HasMany
    {
        return $this->hasMany(StandardCost::class, 'tenant_id');
    }

    public function costIncidents(): HasMany
    {
        return $this->hasMany(CostIncident::class, 'tenant_id');
    }
}
