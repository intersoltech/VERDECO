<?php

namespace Tests\Feature;

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

        $adminWidgets = (new OperationsDashboard())->getWidgets();
        $this->assertContains(PlantCostSummaryWidget::class, $adminWidgets);
        $this->assertContains(CostPeriodSummaryWidget::class, $adminWidgets);
        $this->assertContains(OpenAlertsWidget::class, $adminWidgets);

        $operator = User::factory()->create(['role' => 'plant_operator']);
        $this->actingAs($operator);

        $operatorWidgets = (new OperationsDashboard())->getWidgets();
        $this->assertContains(PlantCostSummaryWidget::class, $operatorWidgets);
        $this->assertContains(OpenAlertsWidget::class, $operatorWidgets);
        $this->assertNotContains(CostPeriodSummaryWidget::class, $operatorWidgets);
    }
}
