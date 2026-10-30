<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactDailyLineCost extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'line_id',
        'production_date',
        'input_kg',
        'output_kg',
        'material_cost',
        'labor_cost',
        'utility_cost',
        'overhead_cost',
        'incident_cost',
        'total_cost',
        'cost_per_kg',
        'yield_percent',
        'batch_count',
        'downtime_minutes',
        'energy_kwh',
    ];

    protected $casts = [
        'production_date' => 'string',
        'input_kg' => 'decimal:4',
        'output_kg' => 'decimal:4',
        'material_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'utility_cost' => 'decimal:2',
        'overhead_cost' => 'decimal:2',
        'incident_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'cost_per_kg' => 'decimal:6',
        'yield_percent' => 'decimal:4',
        'energy_kwh' => 'decimal:4',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'line_id');
    }
}
