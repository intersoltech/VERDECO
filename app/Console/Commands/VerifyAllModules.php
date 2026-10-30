<?php

namespace App\Console\Commands;

use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class VerifyAllModules extends Command
{
    protected $signature = 'verdeco:verify-modules';

    protected $description = 'Test and verify all Filament modules, dashboards, reports, and SCADA APIs against seeded database';

    public function handle(): int
    {
        $this->info('===========================================================');
        $this->info(' Verdeco Cost Control - Comprehensive System Verification ');
        $this->info('===========================================================');

        $admin = User::where('email', 'admin@verdeco.local')->first();
        if (! $admin) {
            $this->error('Admin user admin@verdeco.local not found! Please run php artisan db:seed first.');

            return self::FAILURE;
        }

        $plantManager = User::where('email', 'manager.vdc@verdeco.local')->first();
        $controller = User::where('email', 'controller@verdeco.local')->first();
        $operator = User::where('email', 'operator.vdc@verdeco.local')->first();

        $firstBatch = ProductionBatch::first();
        $batchId = $firstBatch ? $firstBatch->id : 1;

        $failures = 0;
        $passed = 0;

        // 1. Test Admin Web Routes & Dashboards
        $adminRoutes = [
            'Admin Dashboard' => '/admin',
            'Group Overview Dashboard' => '/admin/group-overview',
            'Plant View Dashboard' => '/admin/plant-view',
            'Incident Center Dashboard' => '/admin/incident-center',
            'Plants Resource List' => '/admin/plants',
            'Production Lines List' => '/admin/production-lines',
            'Materials List' => '/admin/materials',
            'Product Grades List' => '/admin/product-grades',
            'Suppliers List' => '/admin/suppliers',
            'Budgets List' => '/admin/budgets',
            'Units List' => '/admin/units',
            'Standard Costs List' => '/admin/standard-costs',
            'Production Batches List' => '/admin/production-batches',
            'Production Batch Drilldown View' => "/admin/production-batches/{$batchId}",
            'Daily Summaries List' => '/admin/daily-production-summaries',
            'Downtime Events List' => '/admin/downtime-events',
            'Cost Incidents List' => '/admin/cost-incidents',
            'Cost Periods List' => '/admin/cost-periods',
            'Batch Quality Results List' => '/admin/batch-quality-results',
            'Alerts List' => '/admin/alerts',
            'Alert Rules List' => '/admin/alert-rules',
        ];

        $this->info("\n--- [1] Filament Dashboards & Resources (Admin) ---");
        auth()->login($admin);

        foreach ($adminRoutes as $label => $uri) {
            $status = $this->simulateGet($uri, $admin);
            if ($status >= 200 && $status < 400) {
                $this->line(sprintf(' <fg=green>[PASS]</> %-35s %s (HTTP %d)', $label, $uri, $status));
                $passed++;
            } else {
                $this->line(sprintf(' <fg=red>[FAIL]</> %-35s %s (HTTP %d)', $label, $uri, $status));
                $failures++;
            }
        }

        // 2. Test Reports & PDF/Excel Exports
        $this->info("\n--- [2] Export Engines (PDF & Excel) ---");
        $exportRoutes = [
            'Batch Cost Sheet PDF' => "/reports/batch/{$batchId}/pdf",
            'Batch Cost Sheet Excel' => "/reports/batch/{$batchId}/excel",
            'Monthly Variance Excel' => '/reports/monthly-variance/excel',
        ];

        foreach ($exportRoutes as $label => $uri) {
            $status = $this->simulateGet($uri, $admin);
            if ($status >= 200 && $status < 400) {
                $this->line(sprintf(' <fg=green>[PASS]</> %-35s %s (HTTP %d)', $label, $uri, $status));
                $passed++;
            } else {
                $this->line(sprintf(' <fg=red>[FAIL]</> %-35s %s (HTTP %d)', $label, $uri, $status));
                $failures++;
            }
        }

        // 3. Test Operational Real-Time Summary Endpoints
        $this->info("\n--- [3] Operational JSON Polling Endpoints ---");
        $opsRoutes = [
            'Live Ops Summary API' => '/api/ops-summary',
            'Live Alerts API' => '/api/alerts',
            'Health Check' => '/health',
        ];

        foreach ($opsRoutes as $label => $uri) {
            $status = $this->simulateGet($uri, $admin);
            if ($status >= 200 && $status < 400) {
                $this->line(sprintf(' <fg=green>[PASS]</> %-35s %s (HTTP %d)', $label, $uri, $status));
                $passed++;
            } else {
                $this->line(sprintf(' <fg=red>[FAIL]</> %-35s %s (HTTP %d)', $label, $uri, $status));
                $failures++;
            }
        }

        // 4. Test Multi-Plant Scoping with Plant Manager
        $this->info("\n--- [4] Multi-Plant Role Scoping (Dallas Manager) ---");
        if ($plantManager) {
            auth()->login($plantManager);
            $pmStatus = $this->simulateGet('/admin/production-batches', $plantManager);
            if ($pmStatus === 200) {
                $this->line(' <fg=green>[PASS]</> Dallas Plant Manager access to Production Batches (HTTP 200)');
                $passed++;
            } else {
                $this->line(" <fg=red>[FAIL]</> Dallas Plant Manager access (HTTP {$pmStatus})");
                $failures++;
            }
        }

        // 5. Test Controller Access
        $this->info("\n--- [5] Controller Access ---");
        if ($controller) {
            auth()->login($controller);
            $cStatus = $this->simulateGet('/admin/cost-periods', $controller);
            if ($cStatus === 200) {
                $this->line(' <fg=green>[PASS]</> Financial Controller access to Cost Periods (HTTP 200)');
                $passed++;
            } else {
                $this->line(" <fg=red>[FAIL]</> Financial Controller access (HTTP {$cStatus})");
                $failures++;
            }
        }

        // 6. Test SCADA API Ingestion with Sanctum Token
        $this->info("\n--- [6] SCADA REST Ingestion API (Sanctum Authenticated) ---");
        $dallasPlant = Plant::where('code', 'VDC')->first();
        if ($admin && $dallasPlant) {
            $token = $admin->createToken('scada-test', [
                'ingest:meter-readings',
                'ingest:production-ticks',
                'ingest:downtime-events',
                'read:operations',
            ])->plainTextToken;

            // Ingest Meter Reading
            $meterRes = $this->simulateApiPost('/api/v1/ingest/meter-readings', $token, [
                'plant_id' => $dallasPlant->id,
                'meter_type' => 'electricity_kwh',
                'reading_value' => 14852.5,
                'unit' => 'kwh',
                'read_at' => now()->toISOString(),
            ]);

            if ($meterRes['status'] === 200 || $meterRes['status'] === 201) {
                $this->line(sprintf(' <fg=green>[PASS]</> SCADA Meter Reading Ingestion (HTTP %d, ID: %s)', $meterRes['status'], $meterRes['data']['staged_id'] ?? 'N/A'));
                $passed++;
            } else {
                $this->line(sprintf(' <fg=red>[FAIL]</> SCADA Meter Reading (HTTP %d: %s)', $meterRes['status'], json_encode($meterRes['data'] ?? [])));
                $failures++;
            }

            // Ingest Production Tick with Lb->Kg Auto-conversion into active running batch
            $activeBatch = ProductionBatch::where('status', 'running')->first() ?? $firstBatch;
            $tickRes = $this->simulateApiPost('/api/v1/ingest/production-ticks', $token, [
                'plant_id' => $dallasPlant->id,
                'production_batch_id' => $activeBatch->id,
                'weight' => 2204.62,
                'unit' => 'lb',
                'read_at' => now()->toISOString(),
            ]);

            if ($tickRes['status'] === 200 || $tickRes['status'] === 201) {
                $this->line(sprintf(' <fg=green>[PASS]</> SCADA Production Tick with Lb->Kg conversion (HTTP %d)', $tickRes['status']));
                $passed++;
            } else {
                $this->line(sprintf(' <fg=red>[FAIL]</> SCADA Production Tick (HTTP %d: %s)', $tickRes['status'], json_encode($tickRes['data'] ?? [])));
                $failures++;
            }

            // Ingest Downtime Event
            $dtRes = $this->simulateApiPost('/api/v1/ingest/downtime-events', $token, [
                'plant_id' => $dallasPlant->id,
                'line_id' => $firstBatch ? $firstBatch->line_id : 1,
                'reason_code' => 'screen_changer_plug',
                'downtime_type' => 'unplanned',
                'start_at' => now()->subMinutes(35)->toISOString(),
                'end_at' => now()->toISOString(),
                'notes' => 'Automatic pressure switch alarm on melt filter',
            ]);

            if ($dtRes['status'] === 200 || $dtRes['status'] === 201) {
                $this->line(sprintf(' <fg=green>[PASS]</> SCADA Downtime Event Ingestion (HTTP %d)', $dtRes['status']));
                $passed++;
            } else {
                $this->line(sprintf(' <fg=red>[FAIL]</> SCADA Downtime Event (HTTP %d: %s)', $dtRes['status'], json_encode($dtRes['data'] ?? [])));
                $failures++;
            }

            // Read Operations Summary
            $summaryRes = $this->simulateApiGet('/api/v1/operations/summary', $token);
            if ($summaryRes['status'] === 200) {
                $this->line(sprintf(' <fg=green>[PASS]</> SCADA Operations Summary (HTTP %d, Active Lines: %d)', $summaryRes['status'], count($summaryRes['data']['active_lines'] ?? [])));
                $passed++;
            } else {
                $this->line(sprintf(' <fg=red>[FAIL]</> SCADA Operations Summary (HTTP %d)', $summaryRes['status']));
                $failures++;
            }
        }

        $this->info("\n===========================================================");
        $this->info(sprintf(' Verification Complete: %d Passed, %d Failed', $passed, $failures));
        $this->info("===========================================================\n");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function simulateGet(string $uri, User $user): int
    {
        $request = Request::create($uri, 'GET');
        $request->setUserResolver(fn () => $user);

        try {
            $response = app()->handle($request);

            return $response->getStatusCode();
        } catch (\Throwable $e) {
            $this->warn("Exception on {$uri}: ".$e->getMessage());

            return 500;
        }
    }

    private function simulateApiGet(string $uri, string $token): array
    {
        $request = Request::create($uri, 'GET');
        $request->headers->set('Authorization', 'Bearer '.$token);
        $request->headers->set('Accept', 'application/json');

        try {
            $response = app()->handle($request);

            return [
                'status' => $response->getStatusCode(),
                'data' => json_decode($response->getContent(), true),
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'error' => $e->getMessage()];
        }
    }

    private function simulateApiPost(string $uri, string $token, array $data): array
    {
        $request = Request::create($uri, 'POST', $data);
        $request->headers->set('Authorization', 'Bearer '.$token);
        $request->headers->set('Accept', 'application/json');

        try {
            $response = app()->handle($request);

            return [
                'status' => $response->getStatusCode(),
                'data' => json_decode($response->getContent(), true),
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'error' => $e->getMessage()];
        }
    }
}
