<?php

namespace App\Domain\Costing\Models;

use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Budget extends Model
{
    use HasFactory, LogsActivity;

    protected static function newFactory(): \Database\Factories\BudgetFactory
    {
        return \Database\Factories\BudgetFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'cost_category_id',
        'period_month',
        'budgeted_cost',
        'budgeted_output_kg',
        'budgeted_cost_per_kg',
        'notes',
    ];

    protected $casts = [
        'period_month' => 'date',
        'budgeted_cost' => 'decimal:2',
        'budgeted_output_kg' => 'decimal:4',
        'budgeted_cost_per_kg' => 'decimal:6',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['budgeted_cost', 'budgeted_output_kg', 'budgeted_cost_per_kg'])
            ->logOnlyDirty();
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function costCategory(): BelongsTo
    {
        return $this->belongsTo(CostCategory::class);
    }
}
