<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Models;

use App\Domain\Organization\Models\Plant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'rule',
        'severity',
        'status',
        'message',
        'triggered_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function acknowledge(?User $user = null): void
    {
        if (in_array($this->status, ['acknowledged', 'resolved'], true)) {
            return;
        }

        $this->status = 'acknowledged';
        $this->acknowledged_at ??= now();
        $this->acknowledged_by ??= $user?->id ?? auth()->id();
        $this->save();
    }

    public function resolve(?User $user = null): void
    {
        if ($this->status === 'resolved') {
            return;
        }

        if ($this->status !== 'acknowledged') {
            $this->acknowledged_at ??= now();
            $this->acknowledged_by ??= $user?->id ?? auth()->id();
        }

        $this->status = 'resolved';
        $this->resolved_at ??= now();
        $this->resolved_by ??= $user?->id ?? auth()->id();
        $this->save();
    }

    public function reopen(?User $user = null): void
    {
        $this->status = 'open';
        $this->resolved_at = null;
        $this->resolved_by = null;
        $this->acknowledged_at = null;
        $this->acknowledged_by = $user?->id ?? auth()->id();
        $this->save();
    }
}
