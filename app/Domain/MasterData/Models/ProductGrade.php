<?php

namespace App\Domain\MasterData\Models;

use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductGrade extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected static function newFactory(): \Database\Factories\ProductGradeFactory
    {
        return \Database\Factories\ProductGradeFactory::new();
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
