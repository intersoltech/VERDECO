<?php

declare(strict_types=1);

namespace App\Services\Costing;

use App\Services\Costing\Contracts\VarianceStrategyInterface;
use App\Services\Costing\Strategies\Variance\LaborEfficiencyVarianceStrategy;
use App\Services\Costing\Strategies\Variance\LaborRateVarianceStrategy;
use App\Services\Costing\Strategies\Variance\MaterialPriceVarianceStrategy;
use App\Services\Costing\Strategies\Variance\MaterialUsageVarianceStrategy;
use App\Services\Costing\Strategies\Variance\OverheadVolumeVarianceStrategy;
use App\Services\Costing\Strategies\Variance\UtilityEfficiencyVarianceStrategy;

final class VarianceStrategyRegistry
{
    /**
     * @var array<string, VarianceStrategyInterface>
     */
    private array $strategies = [];

    public function __construct()
    {
        $this->register(new MaterialPriceVarianceStrategy);
        $this->register(new MaterialUsageVarianceStrategy);
        $this->register(new LaborRateVarianceStrategy);
        $this->register(new LaborEfficiencyVarianceStrategy);
        $this->register(new UtilityEfficiencyVarianceStrategy);
        $this->register(new OverheadVolumeVarianceStrategy);
    }

    public function register(VarianceStrategyInterface $strategy): self
    {
        $this->strategies[$strategy->key()] = $strategy;

        return $this;
    }

    public function get(string $key): ?VarianceStrategyInterface
    {
        return $this->strategies[strtolower(trim($key))] ?? null;
    }

    /**
     * @return array<string, VarianceStrategyInterface>
     */
    public function all(): array
    {
        return $this->strategies;
    }
}
