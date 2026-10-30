<?php

declare(strict_types=1);

namespace App\Services\Costing\Strategies\Variance;

use App\Services\Costing\Contracts\VarianceStrategyInterface;
use App\Services\Costing\DecimalMath;

final class OverheadVolumeVarianceStrategy implements VarianceStrategyInterface
{
    public function key(): string
    {
        return 'overhead_volume';
    }

    public function label(): string
    {
        return 'Fixed Overhead Volume Under-Absorption';
    }

    public function calculate(array $actual, array $standard): string
    {
        $overheadPool = DecimalMath::toDecimal($actual['overhead_pool'] ?? $standard['overhead_pool'] ?? '0');
        $actualKg = DecimalMath::toDecimal($actual['good_output_kg'] ?? '0');
        $standardCapacityKg = DecimalMath::toDecimal($standard['capacity_kg'] ?? $standard['good_output_kg'] ?? $actualKg);

        if (bccomp($standardCapacityKg, '0', 6) === 0 || bccomp($overheadPool, '0', 6) === 0) {
            return '0';
        }

        // Absorption ratio = actualKg / standardCapacityKg
        $ratio = DecimalMath::divide($actualKg, $standardCapacityKg);
        // Under-absorption gap = 1 - ratio
        $unabsorbedFraction = DecimalMath::subtract('1', $ratio);

        return DecimalMath::multiply($overheadPool, $unabsorbedFraction);
    }
}
