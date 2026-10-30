<?php

namespace Tests\Feature;

use App\Domain\Costing\Models\StandardCost;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\DowntimeEvent;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionLine;
use App\Domain\Reporting\Models\FactDailyLineCost;
use App\Domain\Reporting\Models\FactDailyPlantKpi;
use App\Domain\Reporting\Models\FactIncidentSummary;
use App\Domain\Reporting\Models\FactMonthlyVariance;
use App\Models\User;
use App\Services\Costing\ProductionBatchCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactRollupPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Plant $plant;

    private ProductionLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Verdeco Group Global',
            'slug' => 'verdeco',
            'country_code' => 'US',
        ]);

        $this->plant = Plant::create([
            'tenant_id' => $this->organization->id,
            'name' => 'Verdeco Dallas',
            'code' => 'VDC',
            'location' => 'Dallas, TX',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
            'costing_mode' => 'both',
            'is_active' => true,
        ]);

        $this->line = ProductionLine::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'name' => 'Line 01 Extruder',
            'code' => 'VDC-L01',
            'type' => 'extruder',
            'rated_kg_per_hr' => 2200,
            'rated_kw' => 440,
            'is_active' => true,
        ]);

        StandardCost::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'line_id' => $this->line->id,
            'effective_from' => '2026-01-01',
            'standard_yield_percent' => 93.0,
            'kg_per_hour' => 2200,
            'kwh_per_kg' => 0.38,
            'labor_hours_per_ton' => 3.2,
            'material_usage_kg_per_kg' => 1.075,
            'overhead_rate' => 0.16,
        ]);
    }

    public function test_batch_closed_event_updates_fact_tables(): void
    {
        $batch = ProductionBatch::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'line_id' => $this->line->id,
            'batch_number' => 'VDC-BATCH-202610-001',
            'batch_date' => '2026-10-30',
            'status' => 'draft',
            'input_kg' => 10000.0,
            'good_output_kg' => 9300.0,
            'recovery_credit' => 200.0,
            'labor_regular_rate' => 32.0,
            'labor_overtime_rate' => 48.0,
            'labor_regular_hours' => 20.0,
            'labor_overtime_hours' => 2.0,
            'labor_overtime_multiplier' => 1.5,
            'utility_kwh' => 3800.0,
            'utility_rate' => 0.12,
            'overhead_pool' => 1500.0,
            'overhead_basis' => 'per_kg',
            'incident_cost' => 100.0,
            'incident_allocation_rule' => 'direct_tag',
        ]);

        $batch->materials()->create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'material_name' => 'Hot-Washed PET Flake',
            'kg' => 10000.0,
            'unit_price' => 0.68,
        ]);

        app(ProductionBatchCostingService::class)->calculateAndSave($batch);

        $this->assertSame('costed', $batch->fresh()->status);

        // 1. Check FactDailyLineCost
        $lineCost = FactDailyLineCost::where('plant_id', $this->plant->id)
            ->where('line_id', $this->line->id)
            ->where('production_date', '2026-10-30')
            ->first();

        $this->assertNotNull($lineCost);
        $this->assertEquals(10000.0, (float) $lineCost->input_kg);
        $this->assertEquals(9300.0, (float) $lineCost->output_kg);
        $this->assertEquals(93.0, (float) $lineCost->yield_percent);
        $this->assertEquals(1, $lineCost->batch_count);
        $this->assertGreaterThan(0, (float) $lineCost->total_cost);

        // 2. Check FactDailyPlantKpi
        $plantKpi = FactDailyPlantKpi::where('plant_id', $this->plant->id)
            ->where('production_date', '2026-10-30')
            ->first();

        $this->assertNotNull($plantKpi);
        $this->assertEquals(9300.0, (float) $plantKpi->good_output_kg);
        $this->assertEquals(93.0, (float) $plantKpi->yield_percent);
        $this->assertEquals(1, $plantKpi->batch_count);
        $this->assertGreaterThan(0, (float) $plantKpi->actual_cost);
        $this->assertGreaterThan(0, (float) $plantKpi->standard_cost);

        // 3. Check FactMonthlyVariance
        $monthlyVariance = FactMonthlyVariance::where('plant_id', $this->plant->id)
            ->where('period_month', '2026-10-01')
            ->first();

        $this->assertNotNull($monthlyVariance);
        $this->assertEquals(9300.0, (float) $monthlyVariance->output_kg);
        $this->assertGreaterThan(0, (float) $monthlyVariance->actual_cost);
    }

    public function test_incident_approved_event_updates_fact_incident_summary(): void
    {
        $manager = User::factory()->create(['role' => 'plant_manager']);

        $incident = CostIncident::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'line_id' => $this->line->id,
            'incident_type' => 'breakdown',
            'severity' => 'high',
            'root_cause_category' => 'mechanical',
            'description' => 'Melt pump bearing failure',
            'owner' => 'maintenance',
            'status' => 'open',
            'occurred_at' => '2026-10-30 14:00:00',
            'cash_cost' => 3500.00,
            'opportunity_cost' => 1800.00,
            'allocation_rule' => 'by_kg',
        ]);

        $incident->approve($manager);

        $this->assertSame('approved', $incident->fresh()->status);

        $summary = FactIncidentSummary::where('plant_id', $this->plant->id)
            ->where('incident_date', '2026-10-30')
            ->where('incident_type', 'breakdown')
            ->where('root_cause_category', 'mechanical')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(3500.00, (float) $summary->cash_cost);
        $this->assertEquals(1800.00, (float) $summary->opportunity_cost);
        $this->assertEquals(1, $summary->incident_count);
    }

    public function test_downtime_logged_event_updates_line_and_plant_rollups(): void
    {
        $downtime = DowntimeEvent::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'line_id' => $this->line->id,
            'downtime_type' => 'unplanned',
            'reason_code' => 'screen_pack_change',
            'start_at' => '2026-10-30 08:00:00',
            'end_at' => '2026-10-30 09:30:00',
            'duration_minutes' => 90,
            'lost_production_kg' => 3300.00,
            'description' => 'Unscheduled screen pack replacement due to pressure rise',
        ]);

        $lineCost = FactDailyLineCost::where('plant_id', $this->plant->id)
            ->where('line_id', $this->line->id)
            ->where('production_date', '2026-10-30')
            ->first();

        $this->assertNotNull($lineCost);
        $this->assertEquals(90, $lineCost->downtime_minutes);

        $plantKpi = FactDailyPlantKpi::where('plant_id', $this->plant->id)
            ->where('production_date', '2026-10-30')
            ->first();

        $this->assertNotNull($plantKpi);
        $this->assertEquals(90, $plantKpi->downtime_minutes);
    }

    public function test_rollups_rebuild_command_is_idempotent(): void
    {
        // 1. Seed sample batch & incident
        $batch = ProductionBatch::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'line_id' => $this->line->id,
            'batch_number' => 'VDC-BATCH-IDEM-001',
            'batch_date' => '2026-10-30',
            'status' => 'draft',
            'input_kg' => 5000.0,
            'good_output_kg' => 4650.0,
        ]);
        app(ProductionBatchCostingService::class)->calculateAndSave($batch);

        CostIncident::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'incident_type' => 'rework',
            'severity' => 'medium',
            'root_cause_category' => 'color_drift',
            'description' => 'Color L* out of spec',
            'status' => 'approved',
            'occurred_at' => '2026-10-30 11:00:00',
            'cash_cost' => 800.0,
            'opportunity_cost' => 400.0,
        ]);

        // First rebuild
        $this->artisan('rollups:rebuild', [
            '--plant' => $this->plant->id,
            '--start' => '2026-10-01',
            '--end' => '2026-10-31',
        ])->assertSuccessful();

        $countLineCosts = FactDailyLineCost::count();
        $countPlantKpi = FactDailyPlantKpi::count();
        $countVariance = FactMonthlyVariance::count();
        $countIncident = FactIncidentSummary::count();

        // Second rebuild - should produce exact same counts
        $this->artisan('rollups:rebuild', [
            '--plant' => $this->plant->id,
            '--start' => '2026-10-01',
            '--end' => '2026-10-31',
        ])->assertSuccessful();

        $this->assertSame($countLineCosts, FactDailyLineCost::count());
        $this->assertSame($countPlantKpi, FactDailyPlantKpi::count());
        $this->assertSame($countVariance, FactMonthlyVariance::count());
        $this->assertSame($countIncident, FactIncidentSummary::count());
    }

    public function test_rollups_handle_zero_output_batch_without_division_by_zero(): void
    {
        $batch = ProductionBatch::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'line_id' => $this->line->id,
            'batch_number' => 'VDC-PURGE-001',
            'batch_date' => '2026-10-30',
            'status' => 'draft',
            'input_kg' => 500.0,
            'good_output_kg' => 0.0, // Purge batch with 0 good output
            'utility_kwh' => 200.0,
        ]);

        app(ProductionBatchCostingService::class)->calculateAndSave($batch);

        $lineCost = FactDailyLineCost::where('plant_id', $this->plant->id)
            ->where('line_id', $this->line->id)
            ->where('production_date', '2026-10-30')
            ->first();

        $this->assertNotNull($lineCost);
        $this->assertEquals(0.0, (float) $lineCost->output_kg);
        $this->assertEquals(0.0, (float) $lineCost->yield_percent);
        $this->assertEquals(0.0, (float) $lineCost->cost_per_kg);
    }
}
