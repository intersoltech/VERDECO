<?php

namespace Tests\Feature;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOperationalDataAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_json_endpoints_reject_guests(): void
    {
        $this->getJson('/api/ops-summary')->assertUnauthorized();
        $this->getJson('/api/alerts')->assertUnauthorized();
    }

    public function test_guest_homepage_does_not_disclose_demo_credentials(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('admin@verdeco.local')
            ->assertDontSee('password');
    }

    public function test_operational_api_only_returns_data_for_assigned_plants(): void
    {
        $organization = Organization::create([
            'name' => 'Verdeco Group',
            'slug' => 'verdeco',
            'country_code' => 'US',
        ]);

        $assignedPlant = $this->createPlant($organization, 'DAL');
        $unassignedPlant = $this->createPlant($organization, 'ATL');

        foreach ([$assignedPlant, $unassignedPlant] as $plant) {
            Alert::create([
                'tenant_id' => $organization->id,
                'plant_id' => $plant->id,
                'rule' => 'test_alert',
                'severity' => 'high',
                'status' => 'open',
                'message' => 'Test alert',
                'triggered_at' => now(),
            ]);

            CostIncident::create([
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

        $operator = User::factory()->create(['role' => 'plant_operator']);
        $operator->plants()->attach($assignedPlant->id, ['tenant_id' => $organization->id]);

        $this->actingAs($operator)
            ->getJson('/api/ops-summary')
            ->assertOk()
            ->assertJsonPath('totals.plants', 1)
            ->assertJsonPath('totals.active_alerts', 1)
            ->assertJsonPath('totals.open_incidents', 1)
            ->assertJsonCount(1, 'plants')
            ->assertJsonPath('plants.0.code', 'DAL');

        $this->getJson('/api/alerts')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('alerts.0.plant', 'DAL');
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
}
