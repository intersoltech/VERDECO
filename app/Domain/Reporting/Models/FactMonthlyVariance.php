<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactMonthlyVariance extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'period_month',
        'actual_cost',
        'standard_cost',
        'total_variance',
        'material_price_variance',
        'material_usage_variance',
        'labor_rate_variance',
        'labor_efficiency_variance',
        'utility_efficiency_variance',
        'overhead_volume_variance',
        'incident_variance',
        'output_kg',
    ];

    protected $casts = [
        'period_month' => 'date',
        'actual_cost' => 'decimal:2',
        'standard_cost' => 'decimal:2',
        'total_variance' => 'decimal:2',
        'material_price_variance' => 'decimal:2',
        'material_usage_variance' => 'decimal:2',
        'labor_rate_variance' => 'decimal:2',
        'labor_efficiency_variance' => 'decimal:2',
        'utility_efficiency_variance' => 'decimal:2',
        'overhead_volume_variance' => 'decimal:2',
        'incident_variance' => 'decimal:2',
        'output_kg' => 'decimal:4',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }
}
