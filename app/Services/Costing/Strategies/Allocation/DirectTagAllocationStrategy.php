<?php

declare(strict_types=1);

namespace App\Services\Costing\Strategies\Allocation;

use App\Services\Costing\Contracts\AllocationStrategyInterface;
use App\Services\Costing\DecimalMath;

final class DirectTagAllocationStrategy implements AllocationStrategyInterface
{
    public function key(): string
    {
        return 'direct_tag';
    }

    public function allocate(string|int|float $poolAmount, array $context): string
    {
        $pool = DecimalMath::toDecimal($poolAmount);
        $matched = (bool) ($context['matched'] ?? false);

        return $matched ? $pool : '0';
    }

    public function allocateItems(string|int|float $poolAmount, array $items): array
    {
        $pool = DecimalMath::toDecimal($poolAmount);
        $matchedIndices = [];

        foreach ($items as $idx => $item) {
            if (!empty($item['matched']) || !empty($item['direct_tag'])) {
                $matchedIndices[] = $idx;
            }
        }

        $matchedCount = count($matchedIndices);
        if ($matchedCount === 0) {
            return array_map(fn ($item) => ['item' => $item, 'allocated' => '0'], $items);
        }

        $perItem = DecimalMath::divide($pool, (string) $matchedCount);
        $allocations = [];
        $sum = '0';

        foreach ($items as $idx => $item) {
            if (in_array($idx, $matchedIndices, true)) {
                if ($idx === end($matchedIndices)) {
                    $allocated = bcsub($pool, $sum, 6);
                } else {
                    $allocated = $perItem;
                    $sum = bcadd($sum, $allocated, 6);
                }
            } else {
                $allocated = '0';
            }

            $allocations[] = [
                'item' => $item,
                'allocated' => DecimalMath::normalize($allocated),
            ];
        }

        return $allocations;
    }
}
