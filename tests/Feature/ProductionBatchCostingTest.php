<?php

namespace Tests\Feature;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Filament\Resources\ProductionBatchResource;
use App\Models\User;
use App\Services\Costing\ProductionBatchCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionBatchCostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_batch_inputs_are_calculated_and_cost_snapshot_is_persisted(): void
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
            'costing_mode' => 'batch',
            'is_active' => true,
        ]);

        $batch = ProductionBatch::create([
            'tenant_id' => $organization->id,
            'plant_id' => $plant->id,
            'batch_number' => 'DAL-2026-001',
            'batch_date' => '2026-10-30',
            'input_kg' => 1500,
            'good_output_kg' => 1380,
            'recovery_credit' => 50,
            'labor_regular_rate' => 35,
            'labor_overtime_rate' => 52.5,
            'labor_regular_hours' => 16,
            'labor_overtime_hours' => 4,
            'labor_overtime_multiplier' => 1.5,
            'utility_kwh' => 520,
            'utility_rate' => 0.14,
            'overhead_pool' => 240,
            'overhead_basis' => 'per_kg',
            'incident_cost' => 120,
            'incident_allocation_rule' => 'direct_tag',
        ]);

        $batch->materials()->create([
            'tenant_id' => $organization->id,
            'plant_id' => $plant->id,
            'material_name' => 'PET flake',
            'kg' => 1500,
            'unit_price' => 0.70,
        ]);

        $costedBatch = app(ProductionBatchCostingService::class)->calculateAndSave($batch);

        $this->assertSame('1050.00', $costedBatch->fresh()->material_cost);
        $this->assertSame('875.00', $costedBatch->fresh()->labor_cost);
        $this->assertSame('2307.80', $costedBatch->fresh()->net_cost);
        $this->assertSame('92.0000', $costedBatch->fresh()->yield_percent);
        $this->assertSame('1.672318', $costedBatch->fresh()->cost_per_kg);
        $this->assertNotNull($costedBatch->fresh()->calculated_at);
        $this->assertSame('costed', $costedBatch->fresh()->status);

        $manager = User::factory()->create(['role' => 'plant_manager']);
        $manager->plants()->attach($plant->id, ['tenant_id' => $organization->id]);
        $this->actingAs($manager);
        $this->assertFalse(ProductionBatchResource::canEdit($costedBatch->fresh()));
    }
}
