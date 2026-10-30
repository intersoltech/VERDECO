<?php

namespace App\Domain\Quality\Models;

use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BatchQualityResult extends Model
{
    use HasFactory, LogsActivity;

    protected static function newFactory(): \Database\Factories\BatchQualityResultFactory
    {
        return \Database\Factories\BatchQualityResultFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'production_batch_id',
        'tested_at',
        'sample_identifier',
        'intrinsic_viscosity',
        'acetaldehyde_ppm',
        'color_l',
        'color_a',
        'color_b',
        'moisture_ppm',
        'black_specks_count',
        'fines_percent',
        'status',
        'hold_reason',
        'inspector_notes',
        'inspected_by',
    ];

    protected $casts = [
        'tested_at' => 'datetime',
        'intrinsic_viscosity' => 'decimal:4',
        'acetaldehyde_ppm' => 'decimal:4',
        'color_l' => 'decimal:4',
        'color_a' => 'decimal:4',
        'color_b' => 'decimal:4',
        'moisture_ppm' => 'decimal:4',
        'black_specks_count' => 'integer',
        'fines_percent' => 'decimal:4',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'hold_reason', 'intrinsic_viscosity', 'acetaldehyde_ppm', 'color_l', 'color_b'])
            ->logOnlyDirty();
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function isOnHold(): bool
    {
        return in_array($this->status, ['hold', 'quarantined'], true);
    }
}
