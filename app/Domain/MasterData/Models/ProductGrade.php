<?php

namespace App\Domain\MasterData\Models;

use App\Domain\Organization\Models\Plant;
use Database\Factories\ProductGradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductGrade extends Model
{
    use HasFactory;

    protected static function newFactory(): ProductGradeFactory
    {
        return ProductGradeFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'name',
        'code',
        'grade_type',
        'iv_min',
        'iv_max',
        'aa_max',
        'color_l_min',
        'color_l_max',
        'color_b_min',
        'color_b_max',
        'is_active',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
