<?php

namespace Database\Seeders;

use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Costing\Models\Budget;
use App\Domain\Costing\Models\CostCategory;
use App\Domain\Costing\Models\StandardCost;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Incidents\Models\CostIncidentLine;
use App\Domain\MasterData\Models\Material;
use App\Domain\MasterData\Models\MaterialPrice;
use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\MasterData\Models\Supplier;
use App\Domain\MasterData\Models\Unit;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\DowntimeEvent;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionLine;
use App\Domain\Quality\Models\BatchQualityResult;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class VerdecoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Organization::firstOrCreate([
            'slug' => 'verdeco',
        ], [
            'name' => 'Verdeco Group',
            'country_code' => 'US',
        ]);

        $user = User::query()->firstOrCreate(
            ['email' => 'admin@verdeco.local'],
            [
                'name' => 'Verdeco Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        if (! $user->hasRole('admin')) {
            $user->forceFill(['role' => 'admin'])->save();
        }

        $plants = [
            [
                'name' => 'Verdeco Dallas',
                'code' => 'VDC',
                'location' => 'Dallas, TX',
                'timezone' => 'America/Chicago',
                'base_currency' => 'USD',
                'costing_mode' => 'batch',
            ],
            [
                'name' => 'Verdeco Atlanta',
                'code' => 'VGA',
                'location' => 'Atlanta, GA',
                'timezone' => 'America/New_York',
                'base_currency' => 'USD',
                'costing_mode' => 'both',
            ],
            [
                'name' => 'Verdeco Phoenix',
                'code' => 'VPX',
                'location' => 'Phoenix, AZ',
                'timezone' => 'America/Phoenix',
                'base_currency' => 'USD',
                'costing_mode' => 'daily',
            ],
        ];

        $plantModels = [];

        foreach ($plants as $plantData) {
            $plant = Plant::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $plantData['code']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $plantData['name'],
                    'code' => $plantData['code'],
                    'location' => $plantData['location'],
                    'timezone' => $plantData['timezone'],
                    'base_currency' => $plantData['base_currency'],
                    'costing_mode' => $plantData['costing_mode'],
                    'is_active' => true,
                ]
            );

            $plantModels[$plant->code] = $plant;

            $plant->users()->syncWithoutDetaching([$user->id => ['tenant_id' => $tenant->id, 'user_id' => $user->id]]);
        }

        $lineDefinitions = [
            'VDC' => [
                ['name' => 'Line 01 Extruder A', 'code' => 'VDC-L01', 'type' => 'extruder', 'rated_kg_per_hr' => 2200, 'rated_kw' => 440],
                ['name' => 'Line 02 SSP A', 'code' => 'VDC-L02', 'type' => 'ssp', 'rated_kg_per_hr' => 1800, 'rated_kw' => 280],
            ],
            'VGA' => [
                ['name' => 'Line 11 Extruder B', 'code' => 'VGA-L11', 'type' => 'extruder', 'rated_kg_per_hr' => 2600, 'rated_kw' => 500],
                ['name' => 'Line 12 Packaging', 'code' => 'VGA-L12', 'type' => 'packaging', 'rated_kg_per_hr' => 1500, 'rated_kw' => 180],
            ],
            'VPX' => [
                ['name' => 'Line 21 Extruder C', 'code' => 'VPX-L21', 'type' => 'extruder', 'rated_kg_per_hr' => 2400, 'rated_kw' => 390],
            ],
        ];

        foreach ($lineDefinitions as $code => $lines) {
            $plant = $plantModels[$code];

            foreach ($lines as $lineData) {
                ProductionLine::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => $lineData['code']],
                    [
                        'tenant_id' => $tenant->id,
                        'plant_id' => $plant->id,
                        'name' => $lineData['name'],
                        'code' => $lineData['code'],
                        'type' => $lineData['type'],
                        'rated_kg_per_hr' => $lineData['rated_kg_per_hr'],
                        'rated_kw' => $lineData['rated_kw'],
                        'is_active' => true,
                    ]
                );
            }
        }

        foreach ($plantModels as $plant) {
            $supplierCode = sprintf('PETFLAKE-%s', $plant->code);
            $supplier = Supplier::firstOrCreate(
                ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => $supplierCode],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'name' => 'PET Flake Direct',
                    'code' => $supplierCode,
                    'country_code' => 'US',
                    'is_active' => true,
                ]
            );

            $materialCode = sprintf('PET-FLAKE-1-%s', $plant->code);
            $material = Material::firstOrCreate(
                ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => $materialCode],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'supplier_id' => $supplier->id,
                    'name' => 'Hot-Washed PET Flake',
                    'code' => $materialCode,
                    'category' => 'feedstock',
                    'unit' => 'kg',
                    'is_active' => true,
                ]
            );

            MaterialPrice::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'material_id' => $material->id,
                    'currency_code' => 'USD',
                    'effective_from' => now()->subMonth()->startOfDay(),
                ],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'material_id' => $material->id,
                    'currency_code' => 'USD',
                    'unit_price' => 0.68,
                    'effective_from' => now()->subMonth()->startOfDay(),
                    'effective_to' => null,
                ]
            );

            $gradeCode = sprintf('FG-20-%s', $plant->code);
            $grade = ProductGrade::firstOrCreate(
                ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => $gradeCode],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'name' => 'Food Grade 20',
                    'code' => $gradeCode,
                    'grade_type' => 'food_grade',
                    'iv_min' => 0.75,
                    'iv_max' => 0.82,
                    'aa_max' => 3.0,
                    'color_l_min' => 80.0,
                    'color_l_max' => 100.0,
                    'color_b_min' => 0.2,
                    'color_b_max' => 2.5,
                    'is_active' => true,
                ]
            );

            CostCategory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => sprintf('MATERIAL-%s', $plant->code)],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'name' => 'Material',
                    'code' => sprintf('MATERIAL-%s', $plant->code),
                    'kind' => 'direct',
                    'type' => 'variable',
                    'allocation_basis' => 'per_kg',
                    'is_active' => true,
                ]
            );

            CostCategory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => sprintf('ELECTRICITY-%s', $plant->code)],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'name' => 'Electricity',
                    'code' => sprintf('ELECTRICITY-%s', $plant->code),
                    'kind' => 'direct',
                    'type' => 'variable',
                    'allocation_basis' => 'per_kg',
                    'is_active' => true,
                ]
            );

            CostCategory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => sprintf('OVERHEAD-%s', $plant->code)],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'name' => 'Manufacturing Overhead',
                    'code' => sprintf('OVERHEAD-%s', $plant->code),
                    'kind' => 'indirect',
                    'type' => 'fixed',
                    'allocation_basis' => 'per_kg',
                    'is_active' => true,
                ]
            );

            StandardCost::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'product_grade_id' => $grade->id,
                    'effective_from' => now()->subMonth()->startOfDay(),
                ],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'line_id' => null,
                    'product_grade_id' => $grade->id,
                    'effective_from' => now()->subMonth()->startOfDay(),
                    'effective_to' => null,
                    'standard_yield_percent' => 92.5,
                    'kg_per_hour' => 2100,
                    'kwh_per_kg' => 0.38,
                    'labor_hours_per_ton' => 3.2,
                    'material_usage_kg_per_kg' => 1.08,
                    'overhead_rate' => 0.16,
                ]
            );
        }

        $incident = CostIncident::firstOrCreate([
            'tenant_id' => $tenant->id,
            'plant_id' => $plantModels['VDC']->id,
            'incident_type' => 'breakdown',
            'occurred_at' => now()->subDay(),
        ], [
            'tenant_id' => $tenant->id,
            'plant_id' => $plantModels['VDC']->id,
            'line_id' => null,
            'incident_type' => 'breakdown',
            'severity' => 'high',
            'root_cause_category' => 'mechanical',
            'description' => 'Filter system clogged during startup, causing extended downtime and off-spec material.',
            'owner' => 'maintenance',
            'status' => 'approved',
            'occurred_at' => now()->subDay(),
            'cash_cost' => 5400.00,
            'opportunity_cost' => 2600.00,
            'allocation_rule' => 'by_kg',
        ]);

        CostIncidentLine::firstOrCreate(
            ['incident_id' => $incident->id, 'line_type' => 'maintenance'],
            [
                'incident_id' => $incident->id,
                'line_type' => 'maintenance',
                'description' => 'Emergency filter and seal replacement',
                'extra_labor_hours' => 21,
                'extra_labor_rate' => 55.00,
                'overtime_multiplier' => 1.5,
                'headcount' => 3,
                'parts_cost' => 3200.00,
                'contractor_cost' => 1200.00,
                'material_loss_kg' => 1800,
                'material_loss_cost' => 1120.00,
                'rework_cost' => 850.00,
                'rush_freight_cost' => 430.00,
                'utility_spike_cost' => 0.00,
                'quality_hold_cost' => 600.00,
                'other_cost' => 0.00,
                'currency_code' => 'USD',
            ]
        );

        foreach ($plantModels as $plant) {
            AlertRule::firstOrCreate(
                ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => 'incident_cost_threshold'],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'code' => 'incident_cost_threshold',
                    'name' => 'Incident cost threshold',
                    'description' => 'Open incident cash cost exceeds the threshold.',
                    'severity' => 'high',
                    'enabled' => true,
                    'threshold_value' => 2500,
                    'comparison_operator' => 'gt',
                    'cooldown_minutes' => 60,
                    'message_template' => '{plant_code} incident cost is {value} against a threshold of {threshold}.',
                ]
            );

            AlertRule::firstOrCreate(
                ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => 'daily_costing_alert'],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'code' => 'daily_costing_alert',
                    'name' => 'Daily costing alert',
                    'description' => 'Daily costing mode has active incidents.',
                    'severity' => 'medium',
                    'enabled' => true,
                    'threshold_value' => 0,
                    'comparison_operator' => 'gt',
                    'cooldown_minutes' => 60,
                    'message_template' => '{plant_code} has {value} active incident(s) in daily costing mode.',
                ]
            );
        }

        // Units of Measure
        $units = [
            ['code' => 'kg', 'name' => 'Kilogram', 'type' => 'mass', 'conversion_to_base' => 1.0, 'symbol' => 'kg'],
            ['code' => 'lb', 'name' => 'US Pound', 'type' => 'mass', 'conversion_to_base' => 0.45359237, 'symbol' => 'lb'],
            ['code' => 'ton', 'name' => 'Metric Ton', 'type' => 'mass', 'conversion_to_base' => 1000.0, 'symbol' => 'MT'],
            ['code' => 'kwh', 'name' => 'Kilowatt-Hour', 'type' => 'energy', 'conversion_to_base' => 1.0, 'symbol' => 'kWh'],
            ['code' => 'mwh', 'name' => 'Megawatt-Hour', 'type' => 'energy', 'conversion_to_base' => 1000.0, 'symbol' => 'MWh'],
            ['code' => 'hr', 'name' => 'Operating Hour', 'type' => 'time', 'conversion_to_base' => 1.0, 'symbol' => 'hr'],
        ];

        foreach ($units as $u) {
            Unit::firstOrCreate(['code' => $u['code']], $u);
        }

        // Spatie Roles
        $roles = ['admin', 'plant_manager', 'plant_supervisor', 'plant_operator'];
        foreach ($roles as $r) {
            Role::findOrCreate($r, 'web');
        }

        if (! $user->hasRole('admin')) {
            $user->assignRole('admin');
        }

        // Monthly Budgets
        $currentMonth = now()->startOfMonth()->toDateString();
        foreach ($plantModels as $plant) {
            Budget::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'cost_category_id' => null,
                    'period_month' => $currentMonth,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'cost_category_id' => null,
                    'period_month' => $currentMonth,
                    'budgeted_cost' => 185000.00,
                    'budgeted_output_kg' => 250000.0,
                    'budgeted_cost_per_kg' => 0.74,
                    'notes' => 'Q4 Operating Budget Baseline',
                ]
            );
        }

        // Demo Production Batch & Quality Result
        $vdcPlant = $plantModels['VDC'];
        $vdcLine = ProductionLine::where('code', 'VDC-L01')->first();
        $foodGrade = ProductGrade::where('code', 'FG-20-VDC')->first();

        $demoBatch = ProductionBatch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'plant_id' => $vdcPlant->id, 'batch_number' => 'BATCH-VDC-202610-001'],
            [
                'tenant_id' => $tenant->id,
                'plant_id' => $vdcPlant->id,
                'line_id' => $vdcLine?->id,
                'product_grade_id' => $foodGrade?->id,
                'batch_number' => 'BATCH-VDC-202610-001',
                'batch_date' => now()->toDateString(),
                'status' => 'costed',
                'input_kg' => 20000.00,
                'good_output_kg' => 18600.00,
                'recovery_credit' => 450.00,
                'labor_regular_rate' => 32.00,
                'labor_overtime_rate' => 48.00,
                'labor_regular_hours' => 36.00,
                'labor_overtime_hours' => 6.00,
                'utility_kwh' => 7400.00,
                'utility_rate' => 0.12,
                'overhead_pool' => 3000.00,
                'material_cost' => 13600.00,
                'labor_cost' => 1440.00,
                'utility_cost' => 888.00,
                'overhead_cost' => 2976.00,
                'incident_cost' => 250.00,
                'total_cost' => 19154.00,
                'net_cost' => 18704.00,
                'yield_percent' => 93.00,
                'cost_per_kg' => 1.005591,
                'calculated_at' => now(),
            ]
        );

        BatchQualityResult::firstOrCreate(
            ['tenant_id' => $tenant->id, 'plant_id' => $vdcPlant->id, 'production_batch_id' => $demoBatch->id],
            [
                'tenant_id' => $tenant->id,
                'plant_id' => $vdcPlant->id,
                'production_batch_id' => $demoBatch->id,
                'tested_at' => now(),
                'sample_identifier' => 'SMP-VDC-001',
                'intrinsic_viscosity' => 0.7850,
                'acetaldehyde_ppm' => 1.7500,
                'color_l' => 88.50,
                'color_a' => -0.80,
                'color_b' => 1.15,
                'moisture_ppm' => 35.0,
                'black_specks_count' => 1,
                'fines_percent' => 0.08,
                'status' => 'pass',
                'inspector_notes' => 'Food Grade quality parameters verified.',
                'inspected_by' => $user->id,
            ]
        );

        // Demo Downtime Event
        if ($vdcLine) {
            DowntimeEvent::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $vdcPlant->id,
                    'line_id' => $vdcLine->id,
                    'start_at' => now()->subHours(6)->startOfHour(),
                ],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $vdcPlant->id,
                    'line_id' => $vdcLine->id,
                    'cost_incident_id' => $incident->id ?? null,
                    'downtime_type' => 'unplanned',
                    'reason_code' => 'screen_pack_change',
                    'start_at' => now()->subHours(6)->startOfHour(),
                    'end_at' => now()->subHours(6)->startOfHour()->addMinutes(45),
                    'duration_minutes' => 45,
                    'lost_production_kg' => 1650.00,
                    'description' => 'Melt pressure spike required unscheduled screen change.',
                    'resolution_notes' => 'Replaced 40/80 mesh screens and recalibrated pressure transducer.',
                    'reported_by' => $user->id,
                ]
            );
        }
    }
}
