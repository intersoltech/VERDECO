<?php

namespace Database\Seeders;

use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Costing\Models\CostCategory;
use App\Domain\Costing\Models\StandardCost;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Incidents\Models\CostIncidentLine;
use App\Domain\MasterData\Models\Material;
use App\Domain\MasterData\Models\MaterialPrice;
use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\MasterData\Models\Supplier;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionLine;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
            ]
        );

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
    }
}
