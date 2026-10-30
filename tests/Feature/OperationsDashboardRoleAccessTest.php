<?php

namespace Tests\Feature;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Costing\Models\CostPeriod;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Filament\Pages\OperationsDashboard;
use App\Filament\Widgets\CostPeriodSummaryWidget;
use App\Filament\Widgets\OpenAlertsWidget;
use App\Filament\Widgets\PlantCostSummaryWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsDashboardRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_dashboard_hides_admin_only_widgets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $adminWidgets = (new OperationsDashboard)->getWidgets();
        $this->assertContains(PlantCostSummaryWidget::class, $adminWidgets);
        $this->assertContains(CostPeriodSummaryWidget::class, $adminWidgets);
        $this->assertContains(OpenAlertsWidget::class, $adminWidgets);

        $operator = User::factory()->create(['role' => 'plant_operator']);
        $this->actingAs($operator);

        $operatorWidgets = (new OperationsDashboard)->getWidgets();
        $this->assertContains(PlantCostSummaryWidget::class, $operatorWidgets);
        $this->assertContains(OpenAlertsWidget::class, $operatorWidgets);
        $this->assertNotContains(CostPeriodSummaryWidget::class, $operatorWidgets);
    }

    public function test_dashboard_summary_only_counts_assigned_plants_for_operator(): void
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

            CostPeriod::create([
                'tenant_id' => $organization->id,
                'plant_id' => $plant->id,
                'period_start' => now()->startOfMonth(),
                'period_end' => now()->endOfMonth(),
                'status' => 'open',
                'actual_cost' => 100,
                'standard_cost' => 90,
                'variance_cost' => 10,
                'output_kg' => 1000,
            ]);
        }

        $operator = User::factory()->create(['role' => 'plant_operator']);
        $operator->plants()->attach($assignedPlant->id, ['tenant_id' => $organization->id]);
        $this->actingAs($operator);

        $summary = (new OperationsDashboard)->getViewData();

        $this->assertSame(1, $summary['plants']);
        $this->assertSame(1, $summary['openAlerts']);
        $this->assertSame(1, $summary['openIncidents']);
        $this->assertSame(1, $summary['openPeriods']);
    }

    private function createPlant(Organization $organization, string $code): Plant
    {
        return Plant::create([
            'tenant_id' => $organization->id,
            'name' => $code.' Plant',
            'code' => $code,
            'location' => $code,
            'timezone' => 'UTC',
            'base_currency' => 'USD',
            'costing_mode' => 'both',
            'is_active' => true,
        ]);
    }
}
