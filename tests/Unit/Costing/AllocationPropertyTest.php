<?php

namespace Tests\Unit\Costing;

use App\Services\Costing\DecimalMath;
use App\Services\Costing\Strategies\Allocation\ByHoursAllocationStrategy;
use App\Services\Costing\Strategies\Allocation\ByKgAllocationStrategy;
use App\Services\Costing\Strategies\Allocation\EqualSplitAllocationStrategy;
use App\Services\Costing\Strategies\Variance\OverheadVolumeVarianceStrategy;
use Tests\TestCase;

class AllocationPropertyTest extends TestCase
{
    /**
     * Property: For any pool and any number of items with arbitrary weights,
     * the sum of allocated amounts MUST equal the original pool exactly (no penny leakage).
     */
    public function test_by_kg_allocation_sum_always_equals_pool(): void
    {
        $strategy = new ByKgAllocationStrategy();

        $testPools = ['1000.00', '333.33', '9999.99', '0.01', '7654.321', '123456.78'];

        foreach ($testPools as $pool) {
            // Generate 7 items with odd, uneven weights
            $items = [
                ['id' => 1, 'kg' => '137.55'],
                ['id' => 2, 'kg' => '489.12'],
                ['id' => 3, 'kg' => '83.00'],
                ['id' => 4, 'kg' => '912.44'],
                ['id' => 5, 'kg' => '33.19'],
                ['id' => 6, 'kg' => '654.81'],
                ['id' => 7, 'kg' => '211.03'],
            ];

            $results = $strategy->allocateItems($pool, $items);
            $sum = '0';
            foreach ($results as $res) {
                $sum = DecimalMath::add($sum, $res['allocated']);
            }

            $this->assertSame(
                DecimalMath::normalize($pool),
                DecimalMath::normalize($sum),
                "Allocation sum for pool {$pool} must match exactly."
            );
        }
    }

    public function test_by_hours_allocation_sum_always_equals_pool(): void
    {
        $strategy = new ByHoursAllocationStrategy();
        $pool = '5400.00';

        $items = [
            ['line' => 'L1', 'hours' => '12.5'],
            ['line' => 'L2', 'hours' => '7.3'],
            ['line' => 'L3', 'hours' => '19.8'],
            ['line' => 'L4', 'hours' => '4.4'],
        ];

        $results = $strategy->allocateItems($pool, $items);
        $sum = '0';
        foreach ($results as $res) {
            $sum = DecimalMath::add($sum, $res['allocated']);
        }

        $this->assertSame(
            DecimalMath::normalize($pool),
            DecimalMath::normalize($sum)
        );
    }

    public function test_equal_split_sum_always_equals_pool(): void
    {
        $strategy = new EqualSplitAllocationStrategy();
        $pool = '100.00'; // 100 split by 3 is 33.333333, last item gets remainder

        $items = [['id' => 1], ['id' => 2], ['id' => 3]];
        $results = $strategy->allocateItems($pool, $items);

        $sum = '0';
        foreach ($results as $res) {
            $sum = DecimalMath::add($sum, $res['allocated']);
        }

        $this->assertSame(
            DecimalMath::normalize($pool),
            DecimalMath::normalize($sum)
        );
    }

    public function test_fixed_overhead_volume_under_absorption(): void
    {
        $strategy = new OverheadVolumeVarianceStrategy();

        // 100% capacity: actual output = capacity -> 0 variance
        $fullCapacity = $strategy->calculate(
            ['overhead_pool' => '50000', 'good_output_kg' => '100000'],
            ['capacity_kg' => '100000']
        );
        $this->assertSame('0', $fullCapacity);

        // 80% capacity: actual output 80k vs capacity 100k -> 20% unabsorbed ($10,000)
        $underCapacity = $strategy->calculate(
            ['overhead_pool' => '50000', 'good_output_kg' => '80000'],
            ['capacity_kg' => '100000']
        );
        $this->assertSame('10000', $underCapacity);
    }
}
