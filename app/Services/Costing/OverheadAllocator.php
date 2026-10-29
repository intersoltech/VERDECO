<?php

declare(strict_types=1);

namespace App\Services\Costing;

final class OverheadAllocator
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{item: mixed, allocated: string}>
     */
    public function allocateItems(string $pool, string $basis, array $items): array
    {
        $pool = DecimalMath::toDecimal($pool);
        $count = count($items);

        if ($count === 0 || bccomp($pool, '0', 6) === 0) {
            return [];
        }

        if ($basis === 'equal' || $basis === 'per_batch') {
            $share = DecimalMath::divide($pool, (string) $count);

            return array_map(
                fn (mixed $item) => ['item' => $item, 'allocated' => $share],
                $items
            );
        }

        $totalMetric = '0';
        foreach ($items as $item) {
            $metric = DecimalMath::toDecimal($item['kg'] ?? ($item['hours'] ?? 0));
            $totalMetric = bcadd($totalMetric, $metric, 6);
        }

        if (bccomp($totalMetric, '0', 6) === 0) {
            return array_map(
                fn (mixed $item) => ['item' => $item, 'allocated' => '0'],
                $items
            );
        }

        $allocations = [];
        foreach ($items as $item) {
            $metric = DecimalMath::toDecimal($item['kg'] ?? ($item['hours'] ?? 0));
            $share = DecimalMath::divide($metric, $totalMetric);
            $allocated = DecimalMath::multiply($share, $pool);

            $allocations[] = ['item' => $item, 'allocated' => $allocated];
        }

        return $allocations;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function allocateForBatch(string $pool, string $basis, array $context = []): string
    {
        $allocations = $this->allocateItems(
            $pool,
            $basis,
            [[ 'kg' => $context['kg'] ?? '0', 'hours' => $context['hours'] ?? '0' ]]
        );

        if ($allocations === []) {
            return '0';
        }

        return $allocations[0]['allocated'];
    }
}
