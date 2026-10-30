<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactIncidentSummary extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'incident_date',
        'incident_type',
        'root_cause_category',
        'cash_cost',
        'opportunity_cost',
        'incident_count',
    ];

    protected $casts = [
        'incident_date' => 'string',
        'cash_cost' => 'decimal:2',
        'opportunity_cost' => 'decimal:2',
        'incident_count' => 'integer',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }
}
