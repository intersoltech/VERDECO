<?php

namespace App\Console\Commands;

use App\Domain\Costing\Models\Budget;
use App\Domain\Costing\Models\CostCategory;
use App\Domain\Costing\Models\StandardCost;
use App\Domain\MasterData\Models\Material;
use App\Domain\MasterData\Models\MaterialPrice;
use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\MasterData\Models\Supplier;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\DowntimeEvent;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionLine;
use App\Domain\Quality\Models\BatchQualityResult;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedScaleDemo extends Command
{
    protected $signature = 'demo:seed-scale 
                            {--plants=20 : Number of manufacturing plants to seed} 
                            {--years=5 : Number of historical years of batches to seed}
                            {--batches-per-month=2 : Number of sample batches to seed per plant per month for speed}';

    protected $description = 'Seed enterprise scale synthetic manufacturing data (multi-plant, historical batches, downtime, quality)';

    public function handle(): int
    {
        $plantCount = max(1, (int) $this->option('plants'));
        $years = max(1, (int) $this->option('years'));
        $batchesPerMonth = max(1, (int) $this->option('batches-per-month'));

        $this->info("Initializing synthetic scale seeder for {$plantCount} plants across {$years} year(s)...");

        $tenant = Organization::firstOrCreate(
            ['slug' => 'verdeco'],
            ['name' => 'Verdeco Group Global', 'country_code' => 'US']
        );

        $cities = [
            ['Dallas, TX', 'VDC', 'America/Chicago'],
            ['Atlanta, GA', 'VGA', 'America/New_York'],
            ['Phoenix, AZ', 'VPX', 'America/Phoenix'],
            ['Charlotte, NC', 'VNC', 'America/New_York'],
            ['Chicago, IL', 'VIL', 'America/Chicago'],
            ['Houston, TX', 'VHT', 'America/Chicago'],
            ['Columbus, OH', 'VOH', 'America/New_York'],
            ['Indianapolis, IN', 'VIN', 'America/Indiana/Indianapolis'],
            ['Memphis, TN', 'VME', 'America/Chicago'],
            ['Nashville, TN', 'VNA', 'America/Chicago'],
            ['Kansas City, MO', 'VKC', 'America/Chicago'],
            ['Louisville, KY', 'VKY', 'America/New_York'],
            ['Detroit, MI', 'VDT', 'America/Detroit'],
            ['Cincinnati, OH', 'VCI', 'America/New_York'],
            ['Cleveland, OH', 'VCL', 'America/New_York'],
            ['Milwaukee, WI', 'VMW', 'America/Chicago'],
            ['Minneapolis, MN', 'VMN', 'America/Chicago'],
            ['St. Louis, MO', 'VSL', 'America/Chicago'],
            ['Pittsburgh, PA', 'VPA', 'America/New_York'],
            ['Philadelphia, PA', 'VPH', 'America/New_York'],
            ['Denver, CO', 'VDE', 'America/Denver'],
            ['Salt Lake City, UT', 'VSL2', 'America/Denver'],
        ];

        $modes = ['batch', 'daily', 'both'];
        $startDate = Carbon::now()->subYears($years)->startOfMonth();
        $totalMonths = $years * 12;

        $bar = $this->output->createProgressBar($plantCount);
        $bar->start();

        for ($i = 0; $i < $plantCount; $i++) {
            $city = $cities[$i % count($cities)];
            $code = $city[1] . ($i >= count($cities) ? ($i + 1) : '');

            DB::transaction(function () use ($tenant, $city, $code, $modes, $i, $startDate, $totalMonths, $batchesPerMonth) {
                $plant = Plant::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $code],
                    [
                        'tenant_id' => $tenant->id,
                        'name' => "Verdeco " . explode(',', $city[0])[0],
                        'code' => $code,
                        'location' => $city[0],
                        'timezone' => $city[2],
                        'base_currency' => 'USD',
                        'costing_mode' => $modes[$i % count($modes)],
                        'is_active' => true,
                    ]
                );

                // Lines
                $extruder = ProductionLine::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => "{$code}-EXT-01"],
                    [
                        'name' => "Extrusion Line 1",
                        'code' => "{$code}-EXT-01",
                        'type' => 'extruder',
                        'rated_kg_per_hr' => 2400.0,
                        'rated_kw' => 450.0,
                        'is_active' => true,
                    ]
                );

                $ssp = ProductionLine::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => "{$code}-SSP-01"],
                    [
                        'name' => "SSP Reactor 1",
                        'code' => "{$code}-SSP-01",
                        'type' => 'ssp',
                        'rated_kg_per_hr' => 2000.0,
                        'rated_kw' => 300.0,
                        'is_active' => true,
                    ]
                );

                // Supplier & Material
                $supplier = Supplier::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => "SUP-{$code}"],
                    [
                        'name' => "Regional Flake Supply ({$code})",
                        'code' => "SUP-{$code}",
                        'country_code' => 'US',
                        'is_active' => true,
                    ]
                );

                $material = Material::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => "FLAKE-{$code}"],
                    [
                        'supplier_id' => $supplier->id,
                        'name' => "Hot-Washed Clear Flake",
                        'code' => "FLAKE-{$code}",
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
                        'effective_from' => $startDate,
                    ],
                    [
                        'currency_code' => 'USD',
                        'unit_price' => 0.68,
                        'effective_to' => null,
                    ]
                );

                // Product Grade
                $grade = ProductGrade::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'code' => "FG20-{$code}"],
                    [
                        'name' => "Food Grade rPET-20",
                        'code' => "FG20-{$code}",
                        'grade_type' => 'food_grade',
                        'iv_min' => 0.76,
                        'iv_max' => 0.82,
                        'aa_max' => 2.5,
                        'color_l_min' => 85.0,
                        'color_b_max' => 2.0,
                        'is_active' => true,
                    ]
                );

                StandardCost::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'plant_id' => $plant->id,
                        'product_grade_id' => $grade->id,
                        'effective_from' => $startDate,
                    ],
                    [
                        'standard_yield_percent' => 92.5,
                        'kg_per_hour' => 2200.0,
                        'kwh_per_kg' => 0.36,
                        'labor_hours_per_ton' => 3.0,
                        'material_usage_kg_per_kg' => 1.081,
                        'overhead_rate' => 0.15,
                    ]
                );

                // Historical Batches and Downtime
                for ($m = 0; $m < $totalMonths; $m++) {
                    $monthDate = (clone $startDate)->addMonths($m);

                    Budget::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'plant_id' => $plant->id,
                            'period_month' => $monthDate->toDateString(),
                        ],
                        [
                            'budgeted_cost' => 195000.00,
                            'budgeted_output_kg' => 260000.0,
                            'budgeted_cost_per_kg' => 0.75,
                        ]
                    );

                    for ($b = 1; $b <= $batchesPerMonth; $b++) {
                        $batchDate = (clone $monthDate)->addDays($b * 10);
                        $batchNo = sprintf('B-%s-%s-%02d', $code, $batchDate->format('Ym'), $b);

                        $inputKg = 22000.0;
                        $outputKg = 20350.0;
                        $batch = ProductionBatch::firstOrCreate(
                            ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'batch_number' => $batchNo],
                            [
                                'line_id' => $extruder->id,
                                'product_grade_id' => $grade->id,
                                'batch_date' => $batchDate->toDateString(),
                                'status' => 'costed',
                                'input_kg' => $inputKg,
                                'good_output_kg' => $outputKg,
                                'recovery_credit' => 380.00,
                                'labor_regular_rate' => 33.0,
                                'labor_overtime_rate' => 49.5,
                                'labor_regular_hours' => 38.0,
                                'labor_overtime_hours' => 4.0,
                                'utility_kwh' => 7800.0,
                                'utility_rate' => 0.12,
                                'overhead_pool' => 3100.0,
                                'material_cost' => 14960.0,
                                'labor_cost' => 1452.0,
                                'utility_cost' => 936.0,
                                'overhead_cost' => 3052.5,
                                'incident_cost' => 0.0,
                                'total_cost' => 20400.5,
                                'net_cost' => 20020.5,
                                'yield_percent' => 92.5,
                                'cost_per_kg' => 0.983808,
                                'calculated_at' => $batchDate,
                            ]
                        );

                        if ($b === 1) {
                            BatchQualityResult::firstOrCreate(
                                ['tenant_id' => $tenant->id, 'plant_id' => $plant->id, 'production_batch_id' => $batch->id],
                                [
                                    'tested_at' => $batchDate,
                                    'sample_identifier' => "SMP-{$batchNo}",
                                    'intrinsic_viscosity' => 0.7820,
                                    'acetaldehyde_ppm' => 1.80,
                                    'color_l' => 88.0,
                                    'color_b' => 1.2,
                                    'status' => 'pass',
                                ]
                            );
                        }
                    }

                    // 1 monthly downtime event
                    DowntimeEvent::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'plant_id' => $plant->id,
                            'line_id' => $extruder->id,
                            'start_at' => (clone $monthDate)->addDays(15)->setHour(8),
                        ],
                        [
                            'downtime_type' => 'planned',
                            'reason_code' => 'maintenance_screen_cleaning',
                            'end_at' => (clone $monthDate)->addDays(15)->setHour(8)->addMinutes(30),
                            'duration_minutes' => 30,
                            'lost_production_kg' => 1100.0,
                            'description' => 'Scheduled monthly filter pack inspection',
                        ]
                    );
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully generated enterprise scale data across {$plantCount} plants!");

        return self::SUCCESS;
    }
}
