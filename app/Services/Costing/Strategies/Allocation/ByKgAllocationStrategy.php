<?php

declare(strict_types=1);

namespace App\Services\Costing\Strategies\Allocation;

use App\Services\Costing\Contracts\AllocationStrategyInterface;
use App\Services\Costing\DecimalMath;

final class ByKgAllocationStrategy implements AllocationStrategyInterface
{
    public function key(): string
    {
        return 'by_kg';
    }

    public function allocate(string|int|float $poolAmount, array $context): string
    {
        $pool = DecimalMath::toDecimal($poolAmount);
        if (bccomp($pool, '0', 6) === 0) {
            return '0';
        }

        $batchKg = DecimalMath::toDecimal($context['kg'] ?? '0');
        $totalKg = DecimalMath::toDecimal($context['total_kg'] ?? $batchKg);

        if (bccomp($totalKg, '0', 6) === 0) {
            return '0';
        }

        $share = DecimalMath::divide($batchKg, $totalKg);

        return DecimalMath::multiply($pool, $share);
    }

    public function allocateItems(string|int|float $poolAmount, array $items): array
    {
        $pool = DecimalMath::toDecimal($poolAmount);
        $count = count($items);

        if ($count === 0 || bccomp($pool, '0', 6) === 0) {
            return array_map(fn ($item) => ['item' => $item, 'allocated' => '0'], $items);
        }

        $totalKg = '0';
        foreach ($items as $item) {
            $totalKg = bcadd($totalKg, DecimalMath::toDecimal($item['kg'] ?? '0'), 6);
        }

        if (bccomp($totalKg, '0', 6) === 0) {
            return array_map(fn ($item) => ['item' => $item, 'allocated' => '0'], $items);
        }

        $allocations = [];
        $allocatedSum = '0';

        foreach ($items as $idx => $item) {
            // For the last item, assign remaining pool to guarantee sum equals exactly pool
            if ($idx === $count - 1) {
                $allocated = bcsub($pool, $allocatedSum, 6);
            } else {
                $itemKg = DecimalMath::toDecimal($item['kg'] ?? '0');
                $share = DecimalMath::divide($itemKg, $totalKg);
                $allocated = DecimalMath::multiply($pool, $share);
                $allocatedSum = bcadd($allocatedSum, $allocated, 6);
            }

            $allocations[] = [
                'item' => $item,
                'allocated' => DecimalMath::normalize($allocated),
            ];
        }

        return $allocations;
    }
}
