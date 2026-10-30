<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactDailyPlantKpi extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'production_date',
        'input_kg',
        'good_output_kg',
        'yield_percent',
        'actual_cost',
        'standard_cost',
        'variance_cost',
        'cost_per_kg',
        'kwh_per_kg',
        'incident_cash_cost',
        'incident_opportunity_cost',
        'open_incident_count',
        'downtime_minutes',
        'batch_count',
    ];

    protected $casts = [
        'production_date' => 'string',
        'input_kg' => 'decimal:4',
        'good_output_kg' => 'decimal:4',
        'yield_percent' => 'decimal:4',
        'actual_cost' => 'decimal:2',
        'standard_cost' => 'decimal:2',
        'variance_cost' => 'decimal:2',
        'cost_per_kg' => 'decimal:6',
        'kwh_per_kg' => 'decimal:6',
        'incident_cash_cost' => 'decimal:2',
        'incident_opportunity_cost' => 'decimal:2',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }
}
