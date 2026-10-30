<?php

declare(strict_types=1);

namespace App\Services\Costing\Strategies\Allocation;

use App\Services\Costing\Contracts\AllocationStrategyInterface;
use App\Services\Costing\DecimalMath;

final class ByHoursAllocationStrategy implements AllocationStrategyInterface
{
    public function key(): string
    {
        return 'by_hours';
    }

    public function allocate(string|int|float $poolAmount, array $context): string
    {
        $pool = DecimalMath::toDecimal($poolAmount);
        if (bccomp($pool, '0', 6) === 0) {
            return '0';
        }

        $batchHours = DecimalMath::toDecimal($context['hours'] ?? '0');
        $totalHours = DecimalMath::toDecimal($context['total_hours'] ?? $batchHours);

        if (bccomp($totalHours, '0', 6) === 0) {
            return '0';
        }

        $share = DecimalMath::divide($batchHours, $totalHours);

        return DecimalMath::multiply($pool, $share);
    }

    public function allocateItems(string|int|float $poolAmount, array $items): array
    {
        $pool = DecimalMath::toDecimal($poolAmount);
        $count = count($items);

        if ($count === 0 || bccomp($pool, '0', 6) === 0) {
            return array_map(fn ($item) => ['item' => $item, 'allocated' => '0'], $items);
        }

        $totalHours = '0';
        foreach ($items as $item) {
            $totalHours = bcadd($totalHours, DecimalMath::toDecimal($item['hours'] ?? '0'), 6);
        }

        if (bccomp($totalHours, '0', 6) === 0) {
            return array_map(fn ($item) => ['item' => $item, 'allocated' => '0'], $items);
        }

        $allocations = [];
        $allocatedSum = '0';

        foreach ($items as $idx => $item) {
            if ($idx === $count - 1) {
                $allocated = bcsub($pool, $allocatedSum, 6);
            } else {
                $itemHours = DecimalMath::toDecimal($item['hours'] ?? '0');
                $share = DecimalMath::divide($itemHours, $totalHours);
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
