<?php

namespace Tests\Feature;

use App\Filament\Resources\PlantResource;
use App\Filament\Resources\CostIncidentResource;
use App\Filament\Resources\CostPeriodResource;
use App\Filament\Resources\DailyProductionSummaryResource;
use App\Filament\Resources\ProductionBatchResource;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_plant_resource_but_operator_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'plant_operator']);

        $this->actingAs($admin);
        $this->assertTrue(PlantResource::canViewAny());
        $this->assertTrue(PlantResource::canCreate());

        $this->actingAs($operator);
        $this->assertTrue(PlantResource::canViewAny());
        $this->assertFalse(PlantResource::canCreate());
    }

    public function test_role_permissions_match_operational_filament_resources(): void
    {
        $organization = Organization::create([
            'name' => 'Verdeco Group',
            'slug' => 'verdeco',
            'country_code' => 'US',
        ]);
        $plant = $this->createPlant($organization, 'DAL');
        $manager = User::factory()->create(['role' => 'plant_manager']);
        $operator = User::factory()->create(['role' => 'plant_operator']);
        $manager->plants()->attach($plant->id, ['tenant_id' => $organization->id]);
        $operator->plants()->attach($plant->id, ['tenant_id' => $organization->id]);

        $this->actingAs($manager);
        $this->assertTrue(CostIncidentResource::canViewAny());
        $this->assertTrue(CostIncidentResource::canCreate());
        $this->assertTrue(CostPeriodResource::canViewAny());
        $this->assertTrue(ProductionBatchResource::canViewAny());
        $this->assertTrue(ProductionBatchResource::canCreate());
        $this->assertTrue(DailyProductionSummaryResource::canViewAny());
        $this->assertTrue(DailyProductionSummaryResource::canCreate());
        config(['app.env' => 'production']);
        $this->get('/admin/production-batches/create')->assertOk();
        $this->get('/admin/daily-production-summaries/create')->assertOk();

        $this->actingAs($operator);
        $this->assertTrue(CostIncidentResource::canViewAny());
        $this->assertFalse(CostIncidentResource::canCreate());
        $this->assertTrue(CostPeriodResource::canViewAny());
        $this->assertTrue(ProductionBatchResource::canViewAny());
        $this->assertFalse(ProductionBatchResource::canCreate());
        $this->assertTrue(DailyProductionSummaryResource::canViewAny());
        $this->assertFalse(DailyProductionSummaryResource::canCreate());
    }

    public function test_non_admin_resource_queries_only_return_assigned_plant_records(): void
    {
        $organization = Organization::create([
            'name' => 'Verdeco Group',
            'slug' => 'verdeco',
            'country_code' => 'US',
        ]);

        $assignedPlant = $this->createPlant($organization, 'DAL');
        $unassignedPlant = $this->createPlant($organization, 'ATL');
        $assignedIncident = $this->createIncident($organization, $assignedPlant);
        $this->createIncident($organization, $unassignedPlant);

        $operator = User::factory()->create(['role' => 'plant_operator']);
        $operator->plants()->attach($assignedPlant->id, ['tenant_id' => $organization->id]);

        $this->actingAs($operator);

        $this->assertSame([$assignedPlant->id], PlantResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$assignedIncident->id], CostIncidentResource::getEloquentQuery()->pluck('id')->all());
    }

    private function createPlant(Organization $organization, string $code): Plant
    {
        return Plant::create([
            'tenant_id' => $organization->id,
            'name' => $code . ' Plant',
            'code' => $code,
            'location' => $code,
            'timezone' => 'UTC',
            'base_currency' => 'USD',
            'costing_mode' => 'both',
            'is_active' => true,
        ]);
    }

    private function createIncident(Organization $organization, Plant $plant): CostIncident
    {
        return CostIncident::create([
            'tenant_id' => $organization->id,
            'plant_id' => $plant->id,
            'incident_type' => 'breakdown',
            'severity' => 'high',
            'root_cause_category' => 'mechanical',
            'description' => 'Test incident',
            'owner' => 'maintenance',
            'status' => 'open',
            'occurred_at' => now(),
            'cash_cost' => 100,
            'opportunity_cost' => 0,
            'allocation_rule' => 'by_kg',
        ]);
    }
}
