<?php

declare(strict_types=1);

namespace App\Services\Costing\Strategies\Variance;

use App\Services\Costing\Contracts\VarianceStrategyInterface;
use App\Services\Costing\DecimalMath;

final class MaterialPriceVarianceStrategy implements VarianceStrategyInterface
{
    public function key(): string
    {
        return 'material_price';
    }

    public function label(): string
    {
        return 'Material Price Variance';
    }

    public function calculate(array $actual, array $standard): string
    {
        $actualRate = DecimalMath::toDecimal($actual['material_unit_price'] ?? '0');
        $standardRate = DecimalMath::toDecimal($standard['material_unit_price'] ?? '0');
        $actualKg = DecimalMath::toDecimal($actual['material_kg'] ?? '0');

        return DecimalMath::multiply(
            DecimalMath::subtract($actualRate, $standardRate),
            $actualKg
        );
    }
}
