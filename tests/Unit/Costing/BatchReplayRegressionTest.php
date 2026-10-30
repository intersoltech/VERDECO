<?php

declare(strict_types=1);

namespace Tests\Unit\Costing;

use App\Services\Costing\BatchCostCalculator;
use Tests\TestCase;

class BatchReplayRegressionTest extends TestCase
{
    private BatchCostCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BatchCostCalculator();
    }

    /**
     * Fixture 1: Standard Food Grade rPET run (High-Yield Clear Flake)
     */
    public function test_replay_standard_food_grade_batch(): void
    {
        $fixture = [
            'input_kg' => '10000.0000',
            'good_output_kg' => '9400.0000',
            'recovery_credit' => '150.00',
            'materials' => [
                ['kg' => '10000.0000', 'unit_price' => '0.700000'],
            ],
            'labor_regular_rate' => '35.000000',
            'labor_overtime_rate' => '52.500000',
            'labor_regular_hours' => '20.0000',
            'labor_overtime_hours' => '2.0000',
            'labor_overtime_multiplier' => '1.5000',
            'utility_kwh' => '3800.0000',
            'utility_rate' => '0.140000',
            'overhead_pool' => '1128.00',
            'overhead_basis' => 'per_kg',
            'overhead_context' => ['kg' => '9400.0000'],
            'incident_cost' => '0.00',
            'incident_allocation_rule' => 'direct_tag',
            'incident_context' => ['kg' => '9400.0000', 'matched' => false],
        ];

        $result = $this->calculator->calculate($fixture);

        $this->assertSame('7000', $result['material_cost']);
        $this->assertSame('857.5', $result['labor_cost']); // 700 + 157.5
        $this->assertSame('532', $result['utility_cost']);
        $this->assertSame('1128', $result['overhead_cost']);
        $this->assertSame('0', $result['incident_cost']);
        $this->assertSame('150', $result['recovery_credit']);
        $this->assertSame('9517.5', $result['total_cost']);
        $this->assertSame('9367.5', $result['net_cost']);
        $this->assertSame('94', $result['yield_percent']);
        $this->assertSame('0.996542', $result['cost_per_kg']);
    }

    /**
     * Fixture 2: Contaminated Flake Startup Run with Off-Spec Recovery & Rush Incident
     */
    public function test_replay_contaminated_startup_batch_with_incident(): void
    {
        $fixture = [
            'input_kg' => '8000.0000',
            'good_output_kg' => '6800.0000',
            'recovery_credit' => '420.00',
            'materials' => [
                ['kg' => '8000.0000', 'unit_price' => '0.680000'],
            ],
            'labor_regular_rate' => '32.000000',
            'labor_overtime_rate' => '48.000000',
            'labor_regular_hours' => '24.0000',
            'labor_overtime_hours' => '6.0000',
            'labor_overtime_multiplier' => '1.5000',
            'utility_kwh' => '3200.0000',
            'utility_rate' => '0.150000',
            'overhead_pool' => '850.00',
            'overhead_basis' => 'per_kg',
            'overhead_context' => ['kg' => '6800.0000'],
            'incident_cost' => '650.00',
            'incident_allocation_rule' => 'direct_tag',
            'incident_context' => ['kg' => '6800.0000', 'matched' => true],
        ];

        $result = $this->calculator->calculate($fixture);

        $this->assertSame('5440', $result['material_cost']);
        $this->assertSame('1200', $result['labor_cost']); // 768 + 432
        $this->assertSame('480', $result['utility_cost']);
        $this->assertSame('850', $result['overhead_cost']);
        $this->assertSame('650', $result['incident_cost']);
        $this->assertSame('420', $result['recovery_credit']);
        $this->assertSame('8620', $result['total_cost']);
        $this->assertSame('8200', $result['net_cost']);
        $this->assertSame('85', $result['yield_percent']);
        $this->assertSame('1.205882', $result['cost_per_kg']);
    }

    /**
     * Fixture 3: Zero Good Output Edge Case (Startup Burn / Complete Purge)
     */
    public function test_replay_zero_output_purge_batch(): void
    {
        $fixture = [
            'input_kg' => '1200.0000',
            'good_output_kg' => '0.0000',
            'recovery_credit' => '80.00',
            'materials' => [
                ['kg' => '1200.0000', 'unit_price' => '0.650000'],
            ],
            'labor_regular_rate' => '35.000000',
            'labor_overtime_rate' => '0.000000',
            'labor_regular_hours' => '4.0000',
            'labor_overtime_hours' => '0.0000',
            'labor_overtime_multiplier' => '1.0000',
            'utility_kwh' => '400.0000',
            'utility_rate' => '0.140000',
            'overhead_pool' => '0.00',
            'overhead_basis' => 'per_kg',
            'overhead_context' => ['kg' => '0'],
            'incident_cost' => '0.00',
            'incident_allocation_rule' => 'direct_tag',
            'incident_context' => ['kg' => '0', 'matched' => false],
        ];

        $result = $this->calculator->calculate($fixture);

        $this->assertSame('780', $result['material_cost']);
        $this->assertSame('140', $result['labor_cost']);
        $this->assertSame('56', $result['utility_cost']);
        $this->assertSame('896', $result['net_cost']);
        $this->assertSame('0', $result['yield_percent']);
        $this->assertSame('0', $result['cost_per_kg']); // Guarantees zero division guard
    }
}
