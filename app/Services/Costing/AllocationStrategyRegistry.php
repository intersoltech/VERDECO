<?php

declare(strict_types=1);

namespace App\Services\Costing;

use App\Services\Costing\Contracts\AllocationStrategyInterface;
use App\Services\Costing\Strategies\Allocation\ByHoursAllocationStrategy;
use App\Services\Costing\Strategies\Allocation\ByKgAllocationStrategy;
use App\Services\Costing\Strategies\Allocation\DirectTagAllocationStrategy;
use App\Services\Costing\Strategies\Allocation\EqualSplitAllocationStrategy;

final class AllocationStrategyRegistry
{
    /**
     * @var array<string, AllocationStrategyInterface>
     */
    private array $strategies = [];

    public function __construct()
    {
        // Default core strategies
        $this->register(new ByKgAllocationStrategy);
        $this->register(new ByHoursAllocationStrategy);
        $this->register(new DirectTagAllocationStrategy);
        $this->register(new EqualSplitAllocationStrategy);
    }

    public function register(AllocationStrategyInterface $strategy): self
    {
        $this->strategies[$strategy->key()] = $strategy;

        return $this;
    }

    public function get(string $key): AllocationStrategyInterface
    {
        $normalizedKey = strtolower(trim($key));

        // Alias compatibility
        if ($normalizedKey === 'equal' || $normalizedKey === 'per_batch' || $normalizedKey === 'equal_per_day') {
            $normalizedKey = 'equal_split';
        }

        if (! isset($this->strategies[$normalizedKey])) {
            // Default fallback
            return $this->strategies['by_kg'] ?? new ByKgAllocationStrategy;
        }

        return $this->strategies[$normalizedKey];
    }

    /**
     * @return array<string, AllocationStrategyInterface>
     */
    public function all(): array
    {
        return $this->strategies;
    }
}
