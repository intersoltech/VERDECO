<?php

namespace App\Domain\Production\Models;

use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Events\DowntimeLogged;
use App\Models\User;
use Database\Factories\DowntimeEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DowntimeEvent extends Model
{
    use HasFactory, LogsActivity;

    protected static function newFactory(): DowntimeEventFactory
    {
        return DowntimeEventFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'line_id',
        'cost_incident_id',
        'downtime_type',
        'reason_code',
        'start_at',
        'end_at',
        'duration_minutes',
        'lost_production_kg',
        'description',
        'resolution_notes',
        'notes',
        'reported_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'duration_minutes' => 'integer',
        'lost_production_kg' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::saved(function (DowntimeEvent $event): void {
            DowntimeLogged::dispatch($event);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['downtime_type', 'reason_code', 'start_at', 'end_at', 'duration_minutes', 'lost_production_kg'])
            ->logOnlyDirty();
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'line_id');
    }

    public function costIncident(): BelongsTo
    {
        return $this->belongsTo(CostIncident::class, 'cost_incident_id');
    }

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function calculateDuration(): int
    {
        if ($this->start_at && $this->end_at) {
            $this->duration_minutes = (int) $this->start_at->diffInMinutes($this->end_at);
        }

        return $this->duration_minutes;
    }
}
