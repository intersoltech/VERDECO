<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagedMachineReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'line_id',
        'production_batch_id',
        'source',
        'external_id',
        'reading_type',
        'meter_type',
        'reading_value',
        'unit',
        'status',
        'error_message',
        'payload',
        'read_at',
        'processed_at',
    ];

    protected $casts = [
        'reading_value' => 'decimal:4',
        'payload' => 'array',
        'read_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'tenant_id');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'line_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }
}
