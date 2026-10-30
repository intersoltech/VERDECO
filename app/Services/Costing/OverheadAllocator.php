<?php

declare(strict_types=1);

namespace App\Services\Costing;

final class OverheadAllocator
{
    public function __construct(
        private readonly AllocationStrategyRegistry $registry = new AllocationStrategyRegistry
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{item: mixed, allocated: string}>
     */
    public function allocateItems(string $pool, string $basis, array $items): array
    {
        $strategy = $this->registry->get($basis);

        return $strategy->allocateItems($pool, $items);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function allocateForBatch(string $pool, string $basis, array $context = []): string
    {
        $strategy = $this->registry->get($basis);

        return $strategy->allocate($pool, $context);
    }
}
