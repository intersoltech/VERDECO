<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Costing\Models\StandardCost;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Services\Reporting\DashboardCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceAndDisasterRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_cache_service_caches_and_busts_on_plant_invalidation(): void
    {
        $cacheService = app(DashboardCacheService::class);
        $plantId = 101;
        $callCount = 0;

        $computeKpis = function () use (&$callCount) {
            $callCount++;

            return [
                'cost_per_kg' => 0.82,
                'yield_percent' => 93.4,
                'computed_at' => now()->timestamp,
            ];
        };

        // 1. Initial compute & cache
        $firstResult = $cacheService->rememberPlant($plantId, 'kpi_widget', $computeKpis, 300);
        $this->assertSame(1, $callCount);
        $this->assertSame(0.82, $firstResult['cost_per_kg']);

        // 2. Cache hit (callback not executed again)
        $cachedResult = $cacheService->rememberPlant($plantId, 'kpi_widget', $computeKpis, 300);
        $this->assertSame(1, $callCount);
        $this->assertSame($firstResult['computed_at'], $cachedResult['computed_at']);

        // 3. Operational event occurs -> bust plant cache
        $cacheService->invalidatePlant($plantId);

        // 4. Cache miss after invalidation -> callback re-executes
        $recomputedResult = $cacheService->rememberPlant($plantId, 'kpi_widget', $computeKpis, 300);
        $this->assertSame(2, $callCount);
    }

    public function test_performance_indexes_exist_on_database_tables(): void
    {
        // Verify critical composite indexes for high-volume manufacturing throughput
        $batchIndexes = Schema::getIndexes('production_batches');
        $batchIndexNames = collect($batchIndexes)->pluck('name')->all();

        $this->assertContains('batches_plant_status_date_idx', $batchIndexNames);
        $this->assertContains('batches_line_status_idx', $batchIndexNames);

        $incidentIndexes = Schema::getIndexes('cost_incidents');
        $incidentIndexNames = collect($incidentIndexes)->pluck('name')->all();

        $this->assertContains('incidents_plant_status_date_idx', $incidentIndexNames);
    }

    public function test_synthetic_scale_demo_seeder_command_runs_safely(): void
    {
        // Run scale command for 1 plant, 1 year, 1 batch per month
        $this->artisan('demo:seed-scale', [
            '--plants' => 1,
            '--years' => 1,
            '--batches-per-month' => 1,
        ])->assertSuccessful();

        // Verify plant and approved standard costs were created
        $this->assertGreaterThan(0, Plant::count());
        $this->assertGreaterThan(0, ProductionBatch::count());

        $standardCost = StandardCost::latest()->first();
        $this->assertNotNull($standardCost);
        $this->assertSame('approved', $standardCost->status);
    }
}
