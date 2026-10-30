<?php

namespace App\Domain\MasterData\Models;

use App\Services\Costing\DecimalMath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\UnitFactory
    {
        return \Database\Factories\UnitFactory::new();
    }

    protected $fillable = [
        'code',
        'name',
        'type',
        'conversion_to_base',
        'symbol',
        'is_active',
    ];

    protected $casts = [
        'conversion_to_base' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    public static function convert(string|int|float $value, string $fromCode, string $toCode): string
    {
        $fromCode = strtolower(trim($fromCode));
        $toCode = strtolower(trim($toCode));

        if ($fromCode === $toCode) {
            return DecimalMath::toDecimal($value);
        }

        // Fast-path for mass: kg <-> lb
        if ($fromCode === 'kg' && $toCode === 'lb') {
            return DecimalMath::divide($value, '0.45359237'); // 1 lb = 0.45359237 kg
        }

        if ($fromCode === 'lb' && $toCode === 'kg') {
            return DecimalMath::multiply($value, '0.45359237');
        }

        $fromUnit = static::query()->where('code', $fromCode)->first();
        $toUnit = static::query()->where('code', $toCode)->first();

        if (! $fromUnit || ! $toUnit) {
            return DecimalMath::toDecimal($value);
        }

        // Convert fromUnit to base unit, then base unit to toUnit
        $baseValue = DecimalMath::multiply($value, $fromUnit->conversion_to_base);

        return DecimalMath::divide($baseValue, $toUnit->conversion_to_base);
    }
}
