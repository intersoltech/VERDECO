<?php

namespace App\Domain\Costing\Models;

use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyExpense extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'daily_production_summary_id',
        'category',
        'description',
        'amount',
        'currency_code',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (DailyExpense $expense): void {
            $summary = $expense->summary;
            $expense->tenant_id = $summary->tenant_id;
            $expense->plant_id = $summary->plant_id;
        });
    }

    public function summary(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Costing\Models\DailyProductionSummary::class, 'daily_production_summary_id');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}