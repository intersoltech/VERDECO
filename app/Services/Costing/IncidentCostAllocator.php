<?php

declare(strict_types=1);

namespace App\Services\Costing;

final class IncidentCostAllocator
{
    /**
     * @param  array<string, mixed>  $batchContext
     */
    public function allocate(string $incidentCost, string $allocationRule, array $batchContext = []): string
    {
        $incidentCost = DecimalMath::toDecimal($incidentCost);
        $allocationRule = strtolower($allocationRule);

        if (bccomp($incidentCost, '0', 6) === 0) {
            return '0';
        }

        if ($allocationRule === 'direct_tag') {
            return ($batchContext['matched'] ?? false) ? $incidentCost : '0';
        }

        if ($allocationRule === 'equal' || $allocationRule === 'per_batch') {
            return $incidentCost;
        }

        $batchKg = DecimalMath::toDecimal($batchContext['kg'] ?? '0');
        $batchHours = DecimalMath::toDecimal($batchContext['hours'] ?? '0');

        if ($allocationRule === 'by_kg') {
            $totalKg = DecimalMath::toDecimal($batchContext['total_kg'] ?? $batchKg);

            if (bccomp($totalKg, '0', 6) === 0) {
                return '0';
            }

            $share = DecimalMath::divide($batchKg, $totalKg);

            return DecimalMath::multiply($incidentCost, $share);
        }

        if ($allocationRule === 'by_hours') {
            $totalHours = DecimalMath::toDecimal($batchContext['total_hours'] ?? $batchHours);

            if (bccomp($totalHours, '0', 6) === 0) {
                return '0';
            }

            $share = DecimalMath::divide($batchHours, $totalHours);

            return DecimalMath::multiply($incidentCost, $share);
        }

        return '0';
    }
}
