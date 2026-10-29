<?php

declare(strict_types=1);

use App\Services\Costing\BatchCostCalculator;
use App\Services\Costing\DailyCostCalculator;
use App\Services\Costing\IncidentCostAllocator;
use App\Services\Costing\OverheadAllocator;
use App\Services\Costing\VarianceAnalyzer;

it('allocates overhead proportionally by kg', function (): void {
    $allocator = new OverheadAllocator();

    $result = $allocator->allocateItems('100', 'per_kg', [
        ['kg' => '50'],
        ['kg' => '50'],
    ]);

    expect($result)->toHaveCount(2)
        ->and($result[0]['allocated'])->toBe('50')
        ->and($result[1]['allocated'])->toBe('50');
});

it('allocates incident cost by kg', function (): void {
    $allocator = new IncidentCostAllocator();

    $result = $allocator->allocate('100', 'by_kg', [
        'kg' => '25',
        'total_kg' => '100',
    ]);

    expect($result)->toBe('25');
});

it('calculates batch cost with yield and per-kg output', function (): void {
    $calculator = new BatchCostCalculator();

    $batch = [
        'input_kg' => '1500',
        'good_output_kg' => '1380',
        'recovery_credit' => '50',
        'materials' => [
            ['kg' => '1500', 'unit_price' => '0.70'],
        ],
        'labor_regular_rate' => '35',
        'labor_overtime_rate' => '52.5',
        'labor_regular_hours' => '16',
        'labor_overtime_hours' => '4',
        'labor_overtime_multiplier' => '1.5',
        'utility_kwh' => '520',
        'utility_rate' => '0.14',
        'overhead_pool' => '240',
        'overhead_basis' => 'per_kg',
        'overhead_context' => ['kg' => '1380'],
        'incident_cost' => '120',
        'incident_allocation_rule' => 'direct_tag',
        'incident_context' => ['kg' => '1380', 'matched' => true],
    ];

    $result = $calculator->calculate($batch);

    expect($result['material_cost'])->toBe('1050')
        ->and($result['labor_cost'])->toBe('875')
        ->and($result['utility_cost'])->toBe('72.8')
        ->and($result['overhead_cost'])->toBe('240')
        ->and($result['incident_cost'])->toBe('120')
        ->and($result['yield_percent'])->toBe('92')
        ->and($result['cost_per_kg'])->toBe('1.672318')
        ->and($result['net_cost'])->toBe('2307.8');
});

it('calculates daily total with batches and daily expenses', function (): void {
    $calculator = new DailyCostCalculator();

    $result = $calculator->calculate(
        [
            ['net_cost' => '3000'],
            ['net_cost' => '2000'],
        ],
        [
            ['amount' => '800'],
            ['amount' => '200'],
        ],
        [
            ['cash_cost' => '150'],
        ],
        '1000'
    );

    expect($result['batch_total_cost'])->toBe('5000')
        ->and($result['daily_expense_total'])->toBe('1000')
        ->and($result['incident_total_cost'])->toBe('150')
        ->and($result['actual_total_cost'])->toBe('6150')
        ->and($result['cost_per_kg'])->toBe('6.15');
});

it('produces a variance bridge for standard to actual', function (): void {
    $analyzer = new VarianceAnalyzer();

    $result = $analyzer->analyze(
        [
            'material_kg' => '1100',
            'material_unit_price' => '0.75',
            'labor_hours' => '30',
            'labor_rate' => '40',
            'kwh_per_kg' => '0.42',
            'good_output_kg' => '1000',
            'utility_rate' => '0.12',
            'overhead_cost' => '200',
            'incident_cost' => '50',
            'total_cost' => '1250',
        ],
        [
            'material_kg' => '1000',
            'material_unit_price' => '0.70',
            'labor_hours' => '27',
            'labor_rate' => '38',
            'kwh_per_kg' => '0.39',
            'good_output_kg' => '1000',
            'utility_rate' => '0.12',
            'overhead_cost' => '180',
            'incident_cost' => '0',
            'total_cost' => '1100',
        ]
    );

    expect($result['material_price_variance'])->toBe('55')
        ->and($result['material_usage_variance'])->toBe('70')
        ->and($result['labor_rate_variance'])->toBe('60')
        ->and($result['labor_efficiency_variance'])->toBe('114')
        ->and($result['utility_efficiency_variance'])->toBe('36')
        ->and($result['overhead_variance'])->toBe('20')
        ->and($result['incident_variance'])->toBe('50')
        ->and($result['total_variance'])->toBe('150');
});
