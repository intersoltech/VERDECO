<?php

namespace Tests\Feature;

use App\Domain\Costing\Models\Budget;
use App\Domain\MasterData\Models\Unit;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\DowntimeEvent;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionLine;
use App\Domain\Quality\Models\BatchQualityResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalMasterDataAndUnitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_conversion_between_kg_and_lb_is_accurate(): void
    {
        $kgToLb = Unit::convert('1000', 'kg', 'lb');
        $this->assertEqualsWithDelta(2204.6226, (float) $kgToLb, 0.001);

        $lbToKg = Unit::convert($kgToLb, 'lb', 'kg');
        $this->assertEqualsWithDelta(1000.0, (float) $lbToKg, 0.001);
    }

    public function test_downtime_event_calculates_duration_and_persists(): void
    {
        $org = Organization::create(['name' => 'Verdeco Group', 'slug' => 'verdeco']);
        $plant = Plant::create([
            'tenant_id' => $org->id,
            'name' => 'Dallas Plant',
            'code' => 'VDC',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
        ]);
        $line = ProductionLine::create([
            'tenant_id' => $org->id,
            'plant_id' => $plant->id,
            'name' => 'Line 1',
            'code' => 'L01',
            'type' => 'extruder',
        ]);

        $start = now()->subMinutes(90);
        $end = now();

        $downtime = new DowntimeEvent([
            'tenant_id' => $org->id,
            'plant_id' => $plant->id,
            'line_id' => $line->id,
            'downtime_type' => 'unplanned',
            'reason_code' => 'screen_pack_change',
            'start_at' => $start,
            'end_at' => $end,
            'lost_production_kg' => 3300.00,
        ]);

        $downtime->calculateDuration();
        $this->assertSame(90, $downtime->duration_minutes);
        $downtime->save();

        $this->assertDatabaseHas('downtime_events', [
            'plant_id' => $plant->id,
            'line_id' => $line->id,
            'reason_code' => 'screen_pack_change',
            'duration_minutes' => 90,
        ]);
    }

    public function test_quality_result_flags_hold_status_correctly(): void
    {
        $org = Organization::create(['name' => 'Verdeco Group', 'slug' => 'verdeco']);
        $plant = Plant::create([
            'tenant_id' => $org->id,
            'name' => 'Dallas Plant',
            'code' => 'VDC',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
        ]);
        $batch = ProductionBatch::create([
            'tenant_id' => $org->id,
            'plant_id' => $plant->id,
            'batch_number' => 'B-TEST-001',
            'batch_date' => now()->toDateString(),
            'status' => 'costed',
        ]);

        $passResult = BatchQualityResult::create([
            'tenant_id' => $org->id,
            'plant_id' => $plant->id,
            'production_batch_id' => $batch->id,
            'tested_at' => now(),
            'intrinsic_viscosity' => 0.78,
            'acetaldehyde_ppm' => 2.0,
            'color_l' => 90.0,
            'color_b' => 1.5,
            'status' => 'pass',
        ]);
        $this->assertFalse($passResult->isOnHold());

        $holdResult = BatchQualityResult::create([
            'tenant_id' => $org->id,
            'plant_id' => $plant->id,
            'production_batch_id' => $batch->id,
            'tested_at' => now(),
            'intrinsic_viscosity' => 0.65, // Low IV off-spec
            'status' => 'hold',
            'hold_reason' => 'IV below food grade spec',
        ]);
        $this->assertTrue($holdResult->isOnHold());
    }

    public function test_spatie_role_assignment_works_with_user(): void
    {
        $role = Role::findOrCreate('plant_manager', 'web');
        $user = User::factory()->create();

        $user->assignRole($role);
        $this->assertTrue($user->hasRole('plant_manager'));
    }
}
