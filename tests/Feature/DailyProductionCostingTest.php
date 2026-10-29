<?php

namespace Tests\Feature;

use App\Domain\Costing\Models\DailyProductionSummary;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Services\Costing\DailyProductionCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyProductionCostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_cost_rollup_aggregates_costed_batches_and_expenses(): void
    {
        $organization = Organization::create([
            'name' => 'Verdeco Group',
            'slug' => 'verdeco',
            'country_code' => 'US',
        ]);

        $plant = Plant::create([
            'tenant_id' => $organization->id,
            'name' => 'Dallas Plant',
            'code' => 'DAL',
            'location' => 'Dallas, TX',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
            'costing_mode' => 'both',
            'is_active' => true,
        ]);

        $batch = ProductionBatch::create([
            'tenant_id' => $organization->id,
            'plant_id' => $plant->id,
            'batch_number' => 'DAL-2026-101',
            'batch_date' => '2026-10-30',
            'status' => 'costed',
            'input_kg' => 1100,
            'good_output_kg' => 1000,
            'net_cost' => 5000,
            'calculated_at' => now(),
        ]);

        $summary = DailyProductionSummary::create([
            'tenant_id' => $organization->id,
            'plant_id' => $plant->id,
            'production_date' => '2026-10-30',
        ]);

        $summary->expenses()->create([
            'tenant_id' => $organization->id,
            'plant_id' => $plant->id,
            'category' => 'utilities',
            'description' => 'Daily utility charge',
            'amount' => 800,
        ]);

        $summary->expenses()->create([
            'tenant_id' => $organization->id,
            'plant_id' => $plant->id,
            'category' => 'supplies',
            'description' => 'Production supplies',
            'amount' => 200,
        ]);

        $calculated = app(DailyProductionCostingService::class)->calculateAndSave($summary);

        $this->assertSame('5000.00', $calculated->fresh()->batch_total_cost);
        $this->assertSame('1000.00', $calculated->fresh()->daily_expense_total);
        $this->assertSame('6000.00', $calculated->fresh()->actual_total_cost);
        $this->assertSame('1000.0000', $calculated->fresh()->output_kg);
        $this->assertSame('6.000000', $calculated->fresh()->cost_per_kg);
        $this->assertNotNull($calculated->fresh()->calculated_at);
        $this->assertSame($batch->id, $calculated->batches()->first()->id);
    }
}
