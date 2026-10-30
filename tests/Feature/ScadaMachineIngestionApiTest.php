<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Integrations\Models\StagedMachineReading;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionLine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScadaMachineIngestionApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Plant $plantDallas;

    private Plant $plantFontana;

    private ProductionLine $lineDallas;

    private ProductionLine $lineFontana;

    private User $operatorDallas;

    private User $groupAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Verdeco Group Global',
            'slug' => 'verdeco-global',
            'country_code' => 'US',
        ]);

        $this->plantDallas = Plant::create([
            'tenant_id' => $this->organization->id,
            'name' => 'Verdeco Dallas',
            'code' => 'VDC',
            'location' => 'Dallas, TX',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
            'costing_mode' => 'both',
            'is_active' => true,
        ]);

        $this->plantFontana = Plant::create([
            'tenant_id' => $this->organization->id,
            'name' => 'Verdeco Fontana',
            'code' => 'FON',
            'location' => 'Fontana, CA',
            'timezone' => 'America/Los_Angeles',
            'base_currency' => 'USD',
            'costing_mode' => 'both',
            'is_active' => true,
        ]);

        $this->lineDallas = ProductionLine::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'name' => 'Dallas Line 01',
            'code' => 'VDC-L01',
            'type' => 'extruder',
            'rated_kg_per_hr' => 2200,
            'rated_kw' => 450,
            'is_active' => true,
        ]);

        $this->lineFontana = ProductionLine::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantFontana->id,
            'name' => 'Fontana SSP Reactor',
            'code' => 'FON-SSP1',
            'type' => 'ssp',
            'rated_kg_per_hr' => 3000,
            'rated_kw' => 600,
            'is_active' => true,
        ]);

        $this->operatorDallas = User::factory()->create([
            'name' => 'Dallas SCADA Gateway User',
            'role' => 'plant_operator',
        ]);
        $this->operatorDallas->plants()->attach($this->plantDallas->id, ['tenant_id' => $this->organization->id]);

        $this->groupAdmin = User::factory()->create([
            'name' => 'Group Admin',
            'role' => 'admin',
        ]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->postJson('/api/v1/ingest/meter-readings', [
            'plant_code' => 'VDC',
            'meter_type' => 'electricity_kwh',
            'reading_value' => 150.5,
        ]);

        $response->assertStatus(401);
    }

    public function test_token_ability_enforcement(): void
    {
        // Token only has read:operations ability, NOT ingest:meter-readings
        Sanctum::actingAs($this->operatorDallas, ['read:operations']);

        $response = $this->postJson('/api/v1/ingest/meter-readings', [
            'plant_code' => 'VDC',
            'line_code' => 'VDC-L01',
            'meter_type' => 'electricity_kwh',
            'reading_value' => 250.0,
        ]);

        $response->assertStatus(403);
    }

    public function test_meter_reading_ingestion_and_idempotency(): void
    {
        Sanctum::actingAs($this->operatorDallas, ['ingest:meter-readings']);

        $runningBatch = ProductionBatch::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'line_id' => $this->lineDallas->id,
            'batch_number' => 'VDC-LIVE-001',
            'batch_date' => Carbon::today()->toDateString(),
            'status' => 'running',
            'utility_kwh' => 100.0,
        ]);

        $payload = [
            'plant_code' => 'VDC',
            'line_code' => 'VDC-L01',
            'production_batch_id' => $runningBatch->id,
            'meter_type' => 'electricity_kwh',
            'reading_value' => 50.0,
            'unit' => 'kwh',
            'is_delta' => true,
            'external_id' => 'MODBUS-PULSE-9901',
            'read_at' => now()->toIso8601String(),
        ];

        // 1. Initial ingestion -> 201 Created
        $response = $this->postJson('/api/v1/ingest/meter-readings', $payload);
        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'processed')
            ->assertJsonPath('data.reading_value', 50);

        $this->assertDatabaseHas('staged_machine_readings', [
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'external_id' => 'MODBUS-PULSE-9901',
            'status' => 'processed',
        ]);

        // Batch kWh incremented from 100 to 150
        $this->assertSame('150.0000', (string) $runningBatch->fresh()->utility_kwh);

        // 2. Network retry / idempotent replay -> 200 OK duplicate detected without double-counting
        $retryResponse = $this->postJson('/api/v1/ingest/meter-readings', $payload);
        $retryResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'duplicate');

        $this->assertSame(1, StagedMachineReading::where('external_id', 'MODBUS-PULSE-9901')->count());
        $this->assertSame('150.0000', (string) $runningBatch->fresh()->utility_kwh);
    }

    public function test_production_tick_ingestion_with_unit_conversion(): void
    {
        Sanctum::actingAs($this->operatorDallas, ['ingest:production-ticks']);

        $runningBatch = ProductionBatch::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'line_id' => $this->lineDallas->id,
            'batch_number' => 'VDC-LIVE-002',
            'batch_date' => Carbon::today()->toDateString(),
            'status' => 'running',
            'good_output_kg' => 1000.0,
        ]);

        // SCADA sends 2204.62 lb -> should convert to 1000.0000 kg
        $response = $this->postJson('/api/v1/ingest/production-ticks', [
            'plant_code' => 'VDC',
            'line_code' => 'VDC-L01',
            'batch_number' => 'VDC-LIVE-002',
            'weight' => 2204.62,
            'unit' => 'lb',
            'external_id' => 'SCALE-TICK-002',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.original_unit', 'lb');

        // Verify conversion: 1000 + 1000 = 2000 kg
        $this->assertEqualsWithDelta(2000.0, (float) $runningBatch->fresh()->good_output_kg, 0.1);
    }

    public function test_downtime_event_ingestion_creates_operational_downtime(): void
    {
        Sanctum::actingAs($this->operatorDallas, ['ingest:downtime-events']);

        $start = now()->subMinutes(50)->toIso8601String();
        $end = now()->subMinutes(5)->toIso8601String();

        $response = $this->postJson('/api/v1/ingest/downtime-events', [
            'plant_code' => 'VDC',
            'line_code' => 'VDC-L01',
            'downtime_type' => 'unplanned',
            'reason_code' => 'screen_pack_change',
            'start_at' => $start,
            'end_at' => $end,
            'duration_minutes' => 45,
            'notes' => 'Pressure transducer tripped during screen blockage',
            'external_id' => 'SCADA-ALARM-8831',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.duration_minutes', 45)
            ->assertJsonPath('data.reason_code', 'screen_pack_change');

        $this->assertDatabaseHas('downtime_events', [
            'plant_id' => $this->plantDallas->id,
            'line_id' => $this->lineDallas->id,
            'duration_minutes' => 45,
            'reason_code' => 'screen_pack_change',
        ]);
    }

    public function test_multi_plant_token_isolation(): void
    {
        // Operator Dallas only has access to plant Dallas, attempts to ingest for Fontana
        Sanctum::actingAs($this->operatorDallas, ['ingest:meter-readings']);

        $response = $this->postJson('/api/v1/ingest/meter-readings', [
            'plant_code' => 'FON',
            'line_code' => 'FON-SSP1',
            'meter_type' => 'electricity_kwh',
            'reading_value' => 500.0,
        ]);

        $response->assertStatus(403);
    }

    public function test_bulk_csv_upload_adapter(): void
    {
        Sanctum::actingAs($this->groupAdmin, ['ingest:bulk']);

        $csv = <<<'CSV'
timestamp,line_code,reading_type,meter_type,value,unit,external_id
2026-10-30 10:00:00,VDC-L01,meter_reading,electricity_kwh,120.5,kwh,BULK-001
2026-10-30 10:05:00,VDC-L01,meter_reading,electricity_kwh,120.5,kwh,BULK-001
2026-10-30 10:10:00,VDC-L01,production_tick,,500,kg,BULK-002
2026-10-30 10:15:00,VDC-L01,downtime_event,scada_fault,20,minutes,BULK-003
CSV;

        $response = $this->postJson('/api/v1/ingest/bulk-upload', [
            'plant_code' => 'VDC',
            'format' => 'csv',
            'payload' => $csv,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.total', 4)
            ->assertJsonPath('summary.processed', 3)
            ->assertJsonPath('summary.duplicates', 1)
            ->assertJsonPath('summary.errors', 0);
    }

    public function test_operational_summary_and_plants_endpoints(): void
    {
        Sanctum::actingAs($this->operatorDallas, ['read:operations']);

        // 1. Plants endpoint (scoped to Dallas for this operator)
        $plantsResponse = $this->getJson('/api/v1/plants');
        $plantsResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.code', 'VDC');

        // 2. Operations summary endpoint
        $summaryResponse = $this->getJson('/api/v1/operations/summary?plant_code=VDC');
        $summaryResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('plants.0.plant_code', 'VDC');
    }
}
