<?php

declare(strict_types=1);

namespace App\Services\Costing;

final class IncidentCostAllocator
{
    public function __construct(
        private readonly AllocationStrategyRegistry $registry = new AllocationStrategyRegistry()
    ) {}

    /**
     * @param  array<string, mixed>  $batchContext
     */
    public function allocate(string $incidentCost, string $allocationRule, array $batchContext = []): string
    {
        $strategy = $this->registry->get($allocationRule);

        return $strategy->allocate($incidentCost, $batchContext);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{item: mixed, allocated: string}>
     */
    public function allocateItems(string $incidentCost, string $allocationRule, array $items): array
    {
        $strategy = $this->registry->get($allocationRule);

        return $strategy->allocateItems($incidentCost, $items);
    }
}
