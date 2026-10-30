<?php

declare(strict_types=1);

namespace App\Services\Costing\Strategies\Allocation;

use App\Services\Costing\Contracts\AllocationStrategyInterface;
use App\Services\Costing\DecimalMath;

final class EqualSplitAllocationStrategy implements AllocationStrategyInterface
{
    public function key(): string
    {
        return 'equal_split';
    }

    public function allocate(string|int|float $poolAmount, array $context): string
    {
        $pool = DecimalMath::toDecimal($poolAmount);
        $totalItems = max(1, (int) ($context['total_items'] ?? $context['count'] ?? 1));

        return DecimalMath::divide($pool, (string) $totalItems);
    }

    public function allocateItems(string|int|float $poolAmount, array $items): array
    {
        $pool = DecimalMath::toDecimal($poolAmount);
        $count = count($items);

        if ($count === 0 || bccomp($pool, '0', 6) === 0) {
            return array_map(fn ($item) => ['item' => $item, 'allocated' => '0'], $items);
        }

        $baseShare = DecimalMath::divide($pool, (string) $count);
        $allocations = [];
        $sum = '0';

        foreach ($items as $idx => $item) {
            if ($idx === $count - 1) {
                $allocated = bcsub($pool, $sum, 6);
            } else {
                $allocated = $baseShare;
                $sum = bcadd($sum, $allocated, 6);
            }

            $allocations[] = [
                'item' => $item,
                'allocated' => DecimalMath::normalize($allocated),
            ];
        }

        return $allocations;
    }
}
