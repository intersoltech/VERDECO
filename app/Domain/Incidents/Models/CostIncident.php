<?php

declare(strict_types=1);

namespace App\Domain\Incidents\Models;

use App\Domain\Incidents\Events\IncidentApproved;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionLine;
use App\Models\User;
use Database\Factories\CostIncidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CostIncident extends Model
{
    use HasFactory, LogsActivity;

    protected static function newFactory(): CostIncidentFactory
    {
        return CostIncidentFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'line_id',
        'incident_type',
        'severity',
        'root_cause_category',
        'description',
        'owner',
        'reported_by_user_id',
        'status',
        'occurred_at',
        'cash_cost',
        'opportunity_cost',
        'allocation_rule',
        'approved_by',
        'approved_at',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'approved_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'severity',
                'incident_type',
                'cash_cost',
                'opportunity_cost',
                'approved_by',
                'approved_at',
                'closed_by',
                'closed_at',
            ])
            ->logOnlyDirty();
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'line_id');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CostIncidentLine::class, 'incident_id');
    }

    public function approve(?User $user = null): void
    {
        if ($user && $this->reported_by_user_id && $this->reported_by_user_id === $user->id) {
            throw new LogicException('Dual control violation: The user who reported this incident cannot approve it.');
        }

        $this->status = 'approved';
        $this->approved_by = $user?->id ?? $this->approved_by;
        $this->approved_at = $this->approved_at ?? now();
        $this->closed_by = null;
        $this->closed_at = null;
        $this->reopened_by = null;
        $this->reopened_at = null;
        $this->save();

        IncidentApproved::dispatch($this);
    }

    public function close(?User $user = null): void
    {
        $this->status = 'closed';
        $this->closed_by = $user?->id ?? $this->closed_by;
        $this->closed_at = $this->closed_at ?? now();
        $this->save();
    }

    public function reopen(?User $user = null): void
    {
        $this->status = 'open';
        $this->reopened_by = $user?->id ?? $this->reopened_by;
        $this->reopened_at = $this->reopened_at ?? now();
        $this->closed_by = null;
        $this->closed_at = null;
        $this->save();
    }
}
