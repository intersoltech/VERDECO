<?php

declare(strict_types=1);

namespace App\Domain\Costing\Models;

use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandardCost extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\StandardCostFactory
    {
        return \Database\Factories\StandardCostFactory::new();
    }

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

    protected $casts = [
        'effective_from'          => 'datetime',
        'effective_to'            => 'datetime',
        'standard_yield_percent'  => 'decimal:4',
        'kg_per_hour'             => 'decimal:4',
        'kwh_per_kg'              => 'decimal:6',
        'labor_hours_per_ton'     => 'decimal:6',
        'material_usage_kg_per_kg' => 'decimal:6',
        'overhead_rate'           => 'decimal:6',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'line_id');
    }

    public function productGrade(): BelongsTo
    {
        return $this->belongsTo(ProductGrade::class, 'product_grade_id');
    }
}
