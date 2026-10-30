<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Costing\Models\Budget;
use App\Domain\Costing\Models\CostCategory;
use App\Domain\Costing\Models\CostPeriod;
use App\Domain\Costing\Models\DailyExpense;
use App\Domain\Costing\Models\DailyProductionSummary;
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
use App\Domain\Production\Models\ProductionBatchMaterial;
use App\Domain\Production\Models\ProductionLine;
use App\Domain\Quality\Models\BatchQualityResult;
use App\Models\User;
use App\Services\Alerts\AlertEvaluator;
use App\Services\Alerts\AlertStore;
use App\Services\Costing\DailyProductionCostingService;
use App\Services\Reporting\FactRollupService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class VerdecoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding Verdeco Organization & Roles...');

        // 1. Organization (Tenant)
        $tenant = Organization::firstOrCreate(
            ['slug' => 'verdeco'],
            ['name' => 'Verdeco Group Global', 'country_code' => 'US']
        );

        // 2. Spatie Roles
        $roles = ['admin', 'controller', 'plant_manager', 'plant_supervisor', 'plant_operator', 'viewer'];
        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        // 3. User Accounts with clear credentials
        $usersConfig = [
            [
                'email' => 'admin@verdeco.local',
                'name' => 'Verdeco Group Executive (Admin)',
                'role' => 'admin',
                'plants' => ['all'],
            ],
            [
                'email' => 'controller@verdeco.local',
                'name' => 'Sarah Jenkins (Corporate Controller)',
                'role' => 'controller',
                'plants' => ['all'],
            ],
            [
                'email' => 'manager.vdc@verdeco.local',
                'name' => 'Marcus Vance (Dallas Plant Manager)',
                'role' => 'plant_manager',
                'plants' => ['VDC'],
            ],
            [
                'email' => 'manager.vga@verdeco.local',
                'name' => 'Elena Rostova (Atlanta Plant Manager)',
                'role' => 'plant_manager',
                'plants' => ['VGA'],
            ],
            [
                'email' => 'manager.vpx@verdeco.local',
                'name' => 'Carlos Mendez (Phoenix Plant Manager)',
                'role' => 'plant_manager',
                'plants' => ['VPX'],
            ],
            [
                'email' => 'supervisor.vdc@verdeco.local',
                'name' => 'Tom Miller (Dallas Shift Supervisor)',
                'role' => 'plant_supervisor',
                'plants' => ['VDC'],
            ],
            [
                'email' => 'operator.vdc@verdeco.local',
                'name' => 'Jake Reynolds (Dallas Extrusion Operator)',
                'role' => 'plant_operator',
                'plants' => ['VDC'],
            ],
            [
                'email' => 'viewer@verdeco.local',
                'name' => 'Auditor / Board Viewer',
                'role' => 'viewer',
                'plants' => ['all'],
            ],
        ];

        $users = [];
        foreach ($usersConfig as $u) {
            $user = User::query()->firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make('password'),
                    'role' => $u['role'],
                ]
            );

            if ($user->role !== $u['role']) {
                $user->forceFill(['role' => $u['role']])->save();
            }

            if (! $user->hasRole($u['role'])) {
                $user->assignRole($u['role']);
            }

            $users[$u['email']] = $user;
        }

        // 4. Manufacturing Plants
        $plantsData = [
            'VDC' => [
                'name' => 'Verdeco Dallas',
                'code' => 'VDC',
                'location' => 'Dallas, TX',
                'timezone' => 'America/Chicago',
                'base_currency' => 'USD',
                'costing_mode' => 'both',
            ],
            'VGA' => [
                'name' => 'Verdeco Atlanta',
                'code' => 'VGA',
                'location' => 'Atlanta, GA',
                'timezone' => 'America/New_York',
                'base_currency' => 'USD',
                'costing_mode' => 'batch',
            ],
            'VPX' => [
                'name' => 'Verdeco Phoenix',
                'code' => 'VPX',
                'location' => 'Phoenix, AZ',
                'timezone' => 'America/Phoenix',
                'base_currency' => 'USD',
                'costing_mode' => 'daily',
            ],
        ];

        $plantModels = [];
        foreach ($plantsData as $code => $data) {
            $plant = Plant::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                array_merge($data, ['tenant_id' => $tenant->id, 'is_active' => true])
            );
            $plantModels[$code] = $plant;
        }

        // Attach users to plants
        foreach ($usersConfig as $u) {
            $user = $users[$u['email']];
            if (in_array('all', $u['plants'], true)) {
                foreach ($plantModels as $plant) {
                    $plant->users()->syncWithoutDetaching([$user->id => ['tenant_id' => $tenant->id, 'user_id' => $user->id]]);
                }
            } else {
                foreach ($u['plants'] as $pCode) {
                    if (isset($plantModels[$pCode])) {
                        $plantModels[$pCode]->users()->syncWithoutDetaching([$user->id => ['tenant_id' => $tenant->id, 'user_id' => $user->id]]);
                    }
                }
            }
        }

        // 5. Units of Measure
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

        // 6. Production Equipment Lines
        $linesData = [
            'VDC' => [
                ['name' => 'Line 01 Extrusion & Melt Filtration', 'code' => 'VDC-L01', 'type' => 'extruder', 'rated_kg_per_hr' => 2200, 'rated_kw' => 440],
                ['name' => 'Line 02 SSP Condensation Reactor', 'code' => 'VDC-L02', 'type' => 'ssp', 'rated_kg_per_hr' => 1800, 'rated_kw' => 280],
            ],
            'VGA' => [
                ['name' => 'Line 11 High-Speed Extruder B', 'code' => 'VGA-L11', 'type' => 'extruder', 'rated_kg_per_hr' => 2600, 'rated_kw' => 510],
                ['name' => 'Line 12 Bulk Octabin Packaging', 'code' => 'VGA-L12', 'type' => 'packaging', 'rated_kg_per_hr' => 2000, 'rated_kw' => 160],
            ],
            'VPX' => [
                ['name' => 'Line 21 Twin-Screw Extruder C', 'code' => 'VPX-L21', 'type' => 'extruder', 'rated_kg_per_hr' => 2400, 'rated_kw' => 420],
                ['name' => 'Line 22 Vacuum Degassing SSP', 'code' => 'VPX-L22', 'type' => 'ssp', 'rated_kg_per_hr' => 1900, 'rated_kw' => 310],
            ],
        ];

        $lineModels = [];
        foreach ($linesData as $pCode => $lines) {
            $plant = $plantModels[$pCode];
            foreach ($lines as $ld) {
                $line = ProductionLine::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => $ld['code']],
                    array_merge($ld, ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'is_active' => true])
                );
                $lineModels[$ld['code']] = $line;
            }
        }

        // 7. Suppliers, Feedstock Flake, and Material Prices
        $suppliersData = [
            'VDC' => ['code' => 'SUP-TX-RECLAIM', 'name' => 'Texas Post-Consumer Flake Corp', 'price' => 0.68],
            'VGA' => ['code' => 'SUP-GA-WASH', 'name' => 'Southeast Hot-Wash Polymers', 'price' => 0.70],
            'VPX' => ['code' => 'SUP-AZ-PLASTICS', 'name' => 'Sonoran Clean Flake LLC', 'price' => 0.66],
        ];

        $materialModels = [];
        $gradeModels = [];

        foreach ($plantModels as $code => $plant) {
            $supInfo = $suppliersData[$code];
            $supplier = Supplier::firstOrCreate(
                ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => $supInfo['code']],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'name' => $supInfo['name'],
                    'code' => $supInfo['code'],
                    'country_code' => 'US',
                    'is_active' => true,
                ]
            );

            // Feedstock Material
            $matCode = "FLAKE-CLEAR-{$code}";
            $material = Material::firstOrCreate(
                ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => $matCode],
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'supplier_id' => $supplier->id,
                    'name' => 'Hot-Washed Clear PET Flake',
                    'code' => $matCode,
                    'category' => 'feedstock',
                    'unit' => 'kg',
                    'is_active' => true,
                ]
            );
            $materialModels[$code] = $material;

            MaterialPrice::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'material_id' => $material->id,
                    'effective_from' => now()->subMonths(3)->startOfDay(),
                ],
                [
                    'currency_code' => 'USD',
                    'unit_price' => $supInfo['price'],
                    'effective_to' => null,
                ]
            );

            // Product Grades
            $fgGrade = ProductGrade::firstOrCreate(
                ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => "FG-B2B-{$code}"],
                [
                    'name' => 'Food Grade B2B Clear (rPET)',
                    'code' => "FG-B2B-{$code}",
                    'grade_type' => 'food_grade',
                    'iv_min' => 0.78,
                    'iv_max' => 0.84,
                    'aa_max' => 2.5,
                    'color_l_min' => 84.0,
                    'color_l_max' => 96.0,
                    'color_b_min' => 0.2,
                    'color_b_max' => 1.8,
                    'is_active' => true,
                ]
            );
            $gradeModels[$code] = $fgGrade;

            // Cost Categories
            $categories = [
                ['name' => 'Direct Feedstock Flake', 'code' => "MAT-FLAKE-{$code}", 'kind' => 'direct', 'type' => 'variable', 'allocation_basis' => 'per_kg'],
                ['name' => 'Direct Extrusion Electricity', 'code' => "UTIL-ELEC-{$code}", 'kind' => 'direct', 'type' => 'variable', 'allocation_basis' => 'per_kg'],
                ['name' => 'Fixed Plant Overhead', 'code' => "OVH-FIXED-{$code}", 'kind' => 'indirect', 'type' => 'fixed', 'allocation_basis' => 'per_kg'],
            ];
            foreach ($categories as $cat) {
                CostCategory::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => $cat['code']],
                    array_merge($cat, ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'is_active' => true])
                );
            }

            // Approved Standard Costs (with maker-checker audit stamp)
            StandardCost::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'product_grade_id' => $fgGrade->id,
                    'effective_from' => now()->subMonths(3)->startOfMonth(),
                ],
                [
                    'status' => 'approved',
                    'created_by' => $users['controller@verdeco.local']->id,
                    'submitted_by' => $users['controller@verdeco.local']->id,
                    'approved_by' => $users['admin@verdeco.local']->id,
                    'approved_at' => now()->subMonths(3)->startOfMonth()->addHours(2),
                    'standard_yield_percent' => 93.0,
                    'kg_per_hour' => 2200,
                    'kwh_per_kg' => 0.36,
                    'labor_hours_per_ton' => 3.0,
                    'material_usage_kg_per_kg' => 1.0753,
                    'overhead_rate' => 0.15,
                ]
            );

            // Monthly Operating Budget
            Budget::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'period_month' => now()->startOfMonth()->toDateString(),
                ],
                [
                    'budgeted_cost' => 195000.00,
                    'budgeted_output_kg' => 260000.00,
                    'budgeted_cost_per_kg' => 0.75,
                    'notes' => 'Q4 Operating Budget Baseline',
                ]
            );
        }

        // 8. 8 Default Operational Alert Rules
        foreach ($plantModels as $plant) {
            $rules = [
                ['code' => 'incident_cost_threshold', 'name' => 'Incident cost threshold', 'threshold_value' => 2500, 'comparison_operator' => 'gt', 'severity' => 'high'],
                ['code' => 'cost_per_kg_drift', 'name' => 'Cost per kg drift', 'threshold_value' => 5.0, 'comparison_operator' => 'gt', 'severity' => 'high'],
                ['code' => 'yield_below_threshold', 'name' => 'Production yield floor', 'threshold_value' => 91.0, 'comparison_operator' => 'lt', 'severity' => 'high'],
                ['code' => 'overtime_hours_threshold', 'name' => 'Overtime labor threshold', 'threshold_value' => 20.0, 'comparison_operator' => 'gt', 'severity' => 'medium'],
                ['code' => 'unplanned_downtime_threshold', 'name' => 'Unplanned downtime threshold', 'threshold_value' => 60.0, 'comparison_operator' => 'gt', 'severity' => 'high'],
                ['code' => 'energy_intensity_spike', 'name' => 'Energy intensity spike', 'threshold_value' => 0.40, 'comparison_operator' => 'gt', 'severity' => 'medium'],
                ['code' => 'quality_hold_batch', 'name' => 'Batch quality hold', 'threshold_value' => 0, 'comparison_operator' => 'gt', 'severity' => 'critical'],
                ['code' => 'budget_consumed_threshold', 'name' => 'Budget consumption limit', 'threshold_value' => 90.0, 'comparison_operator' => 'gt', 'severity' => 'warning'],
            ];

            foreach ($rules as $r) {
                AlertRule::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => $r['code']],
                    array_merge($r, [
                        'tenant_id' => $tenant->id,
                        'plant_id' => $plant->id,
                        'description' => "Monitors {$r['name']} for plant {$plant->code}",
                        'message_template' => "{$plant->code} {$r['name']} alert triggered with value {value}.",
                        'enabled' => true,
                        'cooldown_minutes' => 60,
                    ])
                );
            }
        }

        // 9. Cost Incidents (Realistic Unplanned Costs)
        $vdcIncident = CostIncident::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'plant_id' => $plantModels['VDC']->id,
                'description' => 'Melt filter screen rupture during polymer pressure surge.',
            ],
            [
                'line_id' => $lineModels['VDC-L01']->id,
                'incident_type' => 'breakdown',
                'severity' => 'high',
                'root_cause_category' => 'mechanical',
                'owner' => 'Maintenance Dept',
                'reported_by_user_id' => $users['operator.vdc@verdeco.local']->id,
                'approved_by' => $users['manager.vdc@verdeco.local']->id,
                'approved_at' => now()->subDays(2),
                'status' => 'approved',
                'occurred_at' => now()->subDays(2)->setHour(14),
                'cash_cost' => 4850.00,
                'opportunity_cost' => 2400.00,
                'allocation_rule' => 'by_kg',
            ]
        );

        CostIncidentLine::firstOrCreate(
            ['incident_id' => $vdcIncident->id, 'line_type' => 'maintenance'],
            [
                'description' => 'Emergency 40/80 wire mesh breaker plate replacement & technician overtime',
                'parts_cost' => 2800.00,
                'contractor_cost' => 850.00,
                'extra_labor_hours' => 16.0,
                'extra_labor_rate' => 45.0,
                'material_loss_kg' => 700.0,
                'material_loss_cost' => 476.0,
                'currency_code' => 'USD',
            ]
        );

        $vgaIncident = CostIncident::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'plant_id' => $plantModels['VGA']->id,
                'description' => 'Main drive inverter VFD tripped on overcurrent due to cooling fan failure.',
            ],
            [
                'line_id' => $lineModels['VGA-L11']->id,
                'incident_type' => 'breakdown',
                'severity' => 'critical',
                'root_cause_category' => 'electrical',
                'owner' => 'Electrical Engineering',
                'reported_by_user_id' => $users['admin@verdeco.local']->id,
                'approved_by' => $users['manager.vga@verdeco.local']->id,
                'approved_at' => now()->subDays(1),
                'status' => 'approved',
                'occurred_at' => now()->subDays(1)->setHour(10),
                'cash_cost' => 3400.00,
                'opportunity_cost' => 1950.00,
                'allocation_rule' => 'by_kg',
            ]
        );

        // 10. Downtime Events
        $downtimes = [
            [
                'plant' => 'VDC',
                'line' => 'VDC-L01',
                'type' => 'unplanned',
                'reason' => 'screen_pack_change',
                'duration' => 50,
                'lost_kg' => 1833.0,
                'notes' => 'Pressure transducer indicated 210 bar before automated screen shift',
                'start_at' => now()->subDays(2)->setHour(14),
            ],
            [
                'plant' => 'VDC',
                'line' => 'VDC-L01',
                'type' => 'planned',
                'reason' => 'die_cleaning',
                'duration' => 60,
                'lost_kg' => 2200.0,
                'notes' => 'Scheduled optical dye head cleaning and vacuum seal replacement',
                'start_at' => now()->subDays(1)->setHour(8),
            ],
            [
                'plant' => 'VGA',
                'line' => 'VGA-L11',
                'type' => 'unplanned',
                'reason' => 'electrical_fault',
                'duration' => 75,
                'lost_kg' => 3250.0,
                'notes' => 'Inverter overtemperature cutout',
                'start_at' => now()->subDays(1)->setHour(10),
            ],
            [
                'plant' => 'VPX',
                'line' => 'VPX-L21',
                'type' => 'unplanned',
                'reason' => 'feed_starvation',
                'duration' => 35,
                'lost_kg' => 1400.0,
                'notes' => 'Pneumatic transfer bridge in bulk silo 3 hopper',
                'start_at' => now()->subHours(12),
            ],
        ];

        foreach ($downtimes as $dt) {
            $plant = $plantModels[$dt['plant']];
            $line = $lineModels[$dt['line']];

            DowntimeEvent::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'line_id' => $line->id,
                    'start_at' => $dt['start_at'],
                ],
                [
                    'downtime_type' => $dt['type'],
                    'reason_code' => $dt['reason'],
                    'start_at' => $dt['start_at'],
                    'end_at' => (clone $dt['start_at'])->addMinutes($dt['duration']),
                    'duration_minutes' => $dt['duration'],
                    'lost_production_kg' => $dt['lost_kg'],
                    'description' => $dt['notes'],
                    'notes' => $dt['notes'],
                    'reported_by' => $users['admin@verdeco.local']->id,
                ]
            );
        }

        // 11. Multi-Plant Historical & Current Production Batches
        $batchesConfig = [
            // Dallas Batches
            [
                'plant' => 'VDC',
                'line' => 'VDC-L01',
                'batch_number' => 'VDC-202610-001',
                'date' => now()->subDays(3)->toDateString(),
                'status' => 'closed',
                'input_kg' => 24000.0,
                'good_output_kg' => 22560.0,
                'recovery_credit' => 350.0,
                'labor_reg_hrs' => 32.0,
                'labor_ot_hrs' => 4.0,
                'labor_reg_rate' => 32.0,
                'labor_ot_rate' => 48.0,
                'utility_kwh' => 8120.0,
                'utility_rate' => 0.12,
                'overhead_pool' => 3600.0,
                'incident_cost' => 650.0,
                'iv' => 0.805,
                'aa' => 1.80,
                'l' => 88.5,
                'b' => 1.15,
                'quality_status' => 'pass',
            ],
            [
                'plant' => 'VDC',
                'line' => 'VDC-L01',
                'batch_number' => 'VDC-202610-002',
                'date' => now()->subDays(2)->toDateString(),
                'status' => 'closed',
                'input_kg' => 22000.0,
                'good_output_kg' => 19800.0, // 90% yield (contaminated screen)
                'recovery_credit' => 600.0,
                'labor_reg_hrs' => 32.0,
                'labor_ot_hrs' => 12.0, // Overtime surge
                'labor_reg_rate' => 32.0,
                'labor_ot_rate' => 48.0,
                'utility_kwh' => 8800.0, // 0.44 kWh/kg energy spike
                'utility_rate' => 0.12,
                'overhead_pool' => 3400.0,
                'incident_cost' => 1200.0,
                'iv' => 0.772,
                'aa' => 2.95,
                'l' => 81.2,
                'b' => 2.45,
                'quality_status' => 'hold', // Quality hold trigger
            ],
            [
                'plant' => 'VDC',
                'line' => 'VDC-L01',
                'batch_number' => 'VDC-202610-003',
                'date' => now()->subDays(1)->toDateString(),
                'status' => 'closed',
                'input_kg' => 25000.0,
                'good_output_kg' => 23625.0, // 94.5% yield (optimum)
                'recovery_credit' => 280.0,
                'labor_reg_hrs' => 32.0,
                'labor_ot_hrs' => 0.0,
                'labor_reg_rate' => 32.0,
                'labor_ot_rate' => 48.0,
                'utility_kwh' => 8270.0, // 0.35 kWh/kg
                'utility_rate' => 0.12,
                'overhead_pool' => 3700.0,
                'incident_cost' => 0.0,
                'iv' => 0.812,
                'aa' => 1.65,
                'l' => 90.1,
                'b' => 0.95,
                'quality_status' => 'pass',
            ],
            [
                'plant' => 'VDC',
                'line' => 'VDC-L01',
                'batch_number' => 'VDC-202610-LIVE',
                'date' => now()->toDateString(),
                'status' => 'running', // Active run currently on floor
                'input_kg' => 18000.0,
                'good_output_kg' => 14200.0,
                'recovery_credit' => 150.0,
                'labor_reg_hrs' => 24.0,
                'labor_ot_hrs' => 0.0,
                'labor_reg_rate' => 32.0,
                'labor_ot_rate' => 48.0,
                'utility_kwh' => 5110.0,
                'utility_rate' => 0.12,
                'overhead_pool' => 2200.0,
                'incident_cost' => 0.0,
                'iv' => 0.801,
                'aa' => 1.70,
                'l' => 89.0,
                'b' => 1.05,
                'quality_status' => 'pass',
            ],

            // Atlanta Batches
            [
                'plant' => 'VGA',
                'line' => 'VGA-L11',
                'batch_number' => 'VGA-202610-101',
                'date' => now()->subDays(2)->toDateString(),
                'status' => 'closed',
                'input_kg' => 28000.0,
                'good_output_kg' => 26320.0, // 94% yield
                'recovery_credit' => 400.0,
                'labor_reg_hrs' => 34.0,
                'labor_ot_hrs' => 2.0,
                'labor_reg_rate' => 30.0,
                'labor_ot_rate' => 45.0,
                'utility_kwh' => 9210.0,
                'utility_rate' => 0.11,
                'overhead_pool' => 4200.0,
                'incident_cost' => 850.0,
                'iv' => 0.795,
                'aa' => 1.90,
                'l' => 87.8,
                'b' => 1.25,
                'quality_status' => 'pass',
            ],
            [
                'plant' => 'VGA',
                'line' => 'VGA-L11',
                'batch_number' => 'VGA-202610-102',
                'date' => now()->subDays(1)->toDateString(),
                'status' => 'closed',
                'input_kg' => 26000.0,
                'good_output_kg' => 24180.0, // 93% yield
                'recovery_credit' => 320.0,
                'labor_reg_hrs' => 32.0,
                'labor_ot_hrs' => 6.0,
                'labor_reg_rate' => 30.0,
                'labor_ot_rate' => 45.0,
                'utility_kwh' => 8700.0,
                'utility_rate' => 0.11,
                'overhead_pool' => 3900.0,
                'incident_cost' => 0.0,
                'iv' => 0.790,
                'aa' => 2.05,
                'l' => 86.5,
                'b' => 1.35,
                'quality_status' => 'pass',
            ],

            // Phoenix Batches
            [
                'plant' => 'VPX',
                'line' => 'VPX-L21',
                'batch_number' => 'VPX-202610-201',
                'date' => now()->subDays(2)->toDateString(),
                'status' => 'closed',
                'input_kg' => 25000.0,
                'good_output_kg' => 23125.0, // 92.5% yield
                'recovery_credit' => 380.0,
                'labor_reg_hrs' => 32.0,
                'labor_ot_hrs' => 4.0,
                'labor_reg_rate' => 28.0,
                'labor_ot_rate' => 42.0,
                'utility_kwh' => 8550.0,
                'utility_rate' => 0.13,
                'overhead_pool' => 3500.0,
                'incident_cost' => 400.0,
                'iv' => 0.785,
                'aa' => 2.10,
                'l' => 85.9,
                'b' => 1.40,
                'quality_status' => 'pass',
            ],
            [
                'plant' => 'VPX',
                'line' => 'VPX-L21',
                'batch_number' => 'VPX-202610-202',
                'date' => now()->subDays(1)->toDateString(),
                'status' => 'closed',
                'input_kg' => 24000.0,
                'good_output_kg' => 22080.0, // 92% yield
                'recovery_credit' => 420.0,
                'labor_reg_hrs' => 32.0,
                'labor_ot_hrs' => 8.0,
                'labor_reg_rate' => 28.0,
                'labor_ot_rate' => 42.0,
                'utility_kwh' => 8610.0,
                'utility_rate' => 0.13,
                'overhead_pool' => 3400.0,
                'incident_cost' => 0.0,
                'iv' => 0.780,
                'aa' => 2.25,
                'l' => 84.8,
                'b' => 1.55,
                'quality_status' => 'pass',
            ],
        ];

        foreach ($batchesConfig as $bc) {
            $plant = $plantModels[$bc['plant']];
            $line = $lineModels[$bc['line']];
            $grade = $gradeModels[$bc['plant']];
            $mat = $materialModels[$bc['plant']];

            // Material cost: flake kg * unit price ($0.68)
            $unitMatPrice = (float) $suppliersData[$bc['plant']]['price'];
            $materialCost = $bc['input_kg'] * $unitMatPrice;

            // Direct labor
            $laborCost = ($bc['labor_reg_hrs'] * $bc['labor_reg_rate']) + ($bc['labor_ot_hrs'] * $bc['labor_ot_rate']);

            // Utilities
            $utilityCost = $bc['utility_kwh'] * $bc['utility_rate'];

            // Total direct cost
            $totalCost = $materialCost + $laborCost + $utilityCost + $bc['overhead_pool'] + $bc['incident_cost'];
            $netCost = $totalCost - $bc['recovery_credit'];
            $costPerKg = $bc['good_output_kg'] > 0 ? $netCost / $bc['good_output_kg'] : 0.0;
            $yieldPercent = $bc['input_kg'] > 0 ? ($bc['good_output_kg'] / $bc['input_kg']) * 100 : 0.0;

            $batch = ProductionBatch::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'batch_number' => $bc['batch_number'],
                ],
                [
                    'line_id' => $line->id,
                    'product_grade_id' => $grade->id,
                    'batch_number' => $bc['batch_number'],
                    'batch_date' => $bc['date'],
                    'status' => $bc['status'],
                    'closed_by' => $bc['status'] === 'closed' ? $users['manager.vdc@verdeco.local']->id : null,
                    'closed_at' => $bc['status'] === 'closed' ? Carbon::parse($bc['date'])->setHour(18) : null,
                    'input_kg' => $bc['input_kg'],
                    'good_output_kg' => $bc['good_output_kg'],
                    'recovery_credit' => $bc['recovery_credit'],
                    'labor_regular_rate' => $bc['labor_reg_rate'],
                    'labor_overtime_rate' => $bc['labor_ot_rate'],
                    'labor_regular_hours' => $bc['labor_reg_hrs'],
                    'labor_overtime_hours' => $bc['labor_ot_hrs'],
                    'utility_kwh' => $bc['utility_kwh'],
                    'utility_rate' => $bc['utility_rate'],
                    'overhead_pool' => $bc['overhead_pool'],
                    'overhead_basis' => 'per_kg',
                    'incident_cost' => $bc['incident_cost'],
                    'incident_allocation_rule' => 'by_kg',
                    'material_cost' => $materialCost,
                    'labor_cost' => $laborCost,
                    'utility_cost' => $utilityCost,
                    'overhead_cost' => $bc['overhead_pool'],
                    'total_cost' => $totalCost,
                    'net_cost' => $netCost,
                    'yield_percent' => $yieldPercent,
                    'cost_per_kg' => $costPerKg,
                    'calculated_at' => now(),
                ]
            );

            // Material Input Line
            ProductionBatchMaterial::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'production_batch_id' => $batch->id,
                    'material_id' => $mat->id,
                ],
                [
                    'material_name' => $mat->name,
                    'kg' => $bc['input_kg'],
                    'unit_price' => $unitMatPrice,
                ]
            );

            // Quality Inspection Result
            BatchQualityResult::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'production_batch_id' => $batch->id,
                ],
                [
                    'tested_at' => Carbon::parse($bc['date'])->setHour(17),
                    'sample_identifier' => sprintf('SMP-%s', $bc['batch_number']),
                    'intrinsic_viscosity' => $bc['iv'],
                    'acetaldehyde_ppm' => $bc['aa'],
                    'color_l' => $bc['l'],
                    'color_a' => -0.75,
                    'color_b' => $bc['b'],
                    'moisture_ppm' => 32.0,
                    'black_specks_count' => $bc['quality_status'] === 'hold' ? 8 : 1,
                    'fines_percent' => $bc['quality_status'] === 'hold' ? 0.35 : 0.05,
                    'status' => $bc['quality_status'],
                    'hold_reason' => $bc['quality_status'] === 'hold' ? 'Color yellowness b* and black specks exceeded specification' : null,
                    'inspector_notes' => $bc['quality_status'] === 'hold' ? 'Quarantined for optical re-sort or downgrade credit' : 'Certified Food Grade release',
                    'inspected_by' => $users['operator.vdc@verdeco.local']->id,
                ]
            );
        }

        // 12. Monthly Cost Period
        foreach ($plantModels as $plant) {
            $prevMonth = now()->subMonth();
            CostPeriod::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'period_start' => $prevMonth->copy()->startOfMonth(),
                    'period_end' => $prevMonth->copy()->endOfMonth(),
                ],
                [
                    'status' => 'closed',
                    'actual_cost' => 189400.00,
                    'standard_cost' => 184500.00,
                    'variance_cost' => 4900.00,
                    'output_kg' => 252000.00,
                    'approved_by' => $users['controller@verdeco.local']->id,
                    'approved_at' => $prevMonth->copy()->endOfMonth()->addDays(2),
                    'closed_by' => $users['admin@verdeco.local']->id,
                    'closed_at' => $prevMonth->copy()->endOfMonth()->addDays(3),
                    'comments' => 'Previous month operational period closed and audited.',
                ]
            );
        }

        // 12b. Daily Production Summaries & Expenses (Batch, Daily, Both)
        $this->command?->info('Seeding Daily Production Summaries & Expenses...');
        $dailyConfig = [
            // Dallas Plant (costing_mode: both)
            [
                'plant' => 'VDC',
                'date' => now()->subDays(3)->toDateString(),
                'expenses' => [
                    ['category' => 'utilities', 'description' => 'Plant water effluent & cooling charge', 'amount' => 420.00],
                    ['category' => 'supplies', 'description' => 'Packaging stretch wrap & gaylord liners', 'amount' => 310.00],
                ],
            ],
            [
                'plant' => 'VDC',
                'date' => now()->subDays(2)->toDateString(),
                'expenses' => [
                    ['category' => 'utilities', 'description' => 'Plant wastewater treatment lab testing', 'amount' => 450.00],
                    ['category' => 'supplies', 'description' => 'Melt filter screen mesh replacement packs', 'amount' => 520.00],
                    ['category' => 'maintenance', 'description' => 'Melt pump seal overhaul technician fee', 'amount' => 750.00],
                ],
            ],
            [
                'plant' => 'VDC',
                'date' => now()->subDays(1)->toDateString(),
                'expenses' => [
                    ['category' => 'supplies', 'description' => 'Forklift battery charging & facility propane', 'amount' => 260.00],
                    ['category' => 'other', 'description' => 'ISO/FDA third-party pellet lab compliance audit', 'amount' => 380.00],
                ],
            ],
            // Atlanta Plant (costing_mode: batch)
            [
                'plant' => 'VGA',
                'date' => now()->subDays(2)->toDateString(),
                'expenses' => [
                    ['category' => 'utilities', 'description' => 'Compressed air and bulk nitrogen supply charge', 'amount' => 360.00],
                    ['category' => 'supplies', 'description' => 'Bulk bag octabin pallets', 'amount' => 290.00],
                ],
            ],
            [
                'plant' => 'VGA',
                'date' => now()->subDays(1)->toDateString(),
                'expenses' => [
                    ['category' => 'maintenance', 'description' => 'Extruder barrel zone heating element inspection', 'amount' => 410.00],
                    ['category' => 'supplies', 'description' => 'Crystallizer dust filter sleeves', 'amount' => 240.00],
                ],
            ],
            // Phoenix Plant (costing_mode: daily)
            [
                'plant' => 'VPX',
                'date' => now()->subDays(2)->toDateString(),
                'expenses' => [
                    ['category' => 'utilities', 'description' => 'Evaporative condenser water conditioning chemical dosage', 'amount' => 380.00],
                    ['category' => 'supplies', 'description' => 'Pellet classifier screening screens', 'amount' => 320.00],
                ],
            ],
            [
                'plant' => 'VPX',
                'date' => now()->subDays(1)->toDateString(),
                'expenses' => [
                    ['category' => 'maintenance', 'description' => 'Underwater pelletizer die face resurfacing fee', 'amount' => 550.00],
                    ['category' => 'supplies', 'description' => 'Bulk truck loadout sampling containers', 'amount' => 190.00],
                ],
            ],
        ];

        $dailyCostingService = app(DailyProductionCostingService::class);
        foreach ($dailyConfig as $cfg) {
            $plant = $plantModels[$cfg['plant']];
            $summary = DailyProductionSummary::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plant_id' => $plant->id,
                    'production_date' => $cfg['date'],
                ],
                [
                    'status' => 'draft',
                ]
            );

            foreach ($cfg['expenses'] as $exp) {
                DailyExpense::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'plant_id' => $plant->id,
                        'daily_production_summary_id' => $summary->id,
                        'category' => $exp['category'],
                        'description' => $exp['description'],
                    ],
                    [
                        'amount' => $exp['amount'],
                        'currency_code' => 'USD',
                    ]
                );
            }

            // Calculate and rollup daily production costs
            $dailyCostingService->calculateAndSave($summary);
        }

        // 13. Rebuild Rollup Analytical Read-Models
        $this->command?->info('Rebuilding analytical fact rollups...');
        app(FactRollupService::class)->rebuild();

        // 14. Evaluate & Sync Operational Alerts
        $this->command?->info('Evaluating operational alert rules...');
        $evaluator = app(AlertEvaluator::class);
        $alerts = $evaluator->evaluate();
        app(AlertStore::class)->sync($alerts);

        $this->command?->info('Verdeco manufacturing cost control demo data seeded successfully!');
    }
}
