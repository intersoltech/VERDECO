<?php

require __DIR__.'/vendor/autoload.php';

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

$calc = new App\Services\Costing\BatchCostCalculator();
var_export($calc->calculate($batch));
