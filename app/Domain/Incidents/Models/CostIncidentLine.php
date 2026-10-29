<?php

namespace App\Domain\Incidents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostIncidentLine extends Model
{
    protected $fillable = [
        'incident_id',
        'line_type',
        'description',
        'extra_labor_hours',
        'extra_labor_rate',
        'overtime_multiplier',
        'headcount',
        'parts_cost',
        'contractor_cost',
        'material_loss_kg',
        'material_loss_cost',
        'rework_cost',
        'rush_freight_cost',
        'utility_spike_cost',
        'quality_hold_cost',
        'other_cost',
        'currency_code',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(CostIncident::class, 'incident_id');
    }
}
