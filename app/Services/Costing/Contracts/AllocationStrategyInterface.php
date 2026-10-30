<?php

declare(strict_types=1);

namespace App\Services\Costing\Contracts;

interface AllocationStrategyInterface
{
    /**
     * Unique identifier code for the strategy (e.g. by_kg, by_hours, direct_tag, equal_split).
     */
    public function key(): string;

    /**
     * Allocate a pool amount to a specific batch or unit based on context.
     *
     * @param  array<string, mixed>  $context
     */
    public function allocate(string|int|float $poolAmount, array $context): string;

    /**
     * Allocate a pool amount across multiple items, guaranteeing the sum of allocations equals the pool (no leakage).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array{item: mixed, allocated: string}>
     */
    public function allocateItems(string|int|float $poolAmount, array $items): array;
}
