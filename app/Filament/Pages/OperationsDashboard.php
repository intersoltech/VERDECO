<?php

namespace App\Filament\Pages;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Plant;
use App\Domain\Costing\Models\CostPeriod;
use App\Filament\Widgets\CostPeriodSummaryWidget;
use App\Filament\Widgets\OpenAlertsWidget;
use App\Filament\Widgets\PlantCostSummaryWidget;
use App\Filament\Widgets\PlantPerformanceTableWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Schema;

class OperationsDashboard extends BaseDashboard
{
    protected static ?string $title = 'Operations overview';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $slug = 'operations-dashboard';

    public function getWidgets(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $widgets = [
            PlantCostSummaryWidget::class,
            OpenAlertsWidget::class,
            PlantPerformanceTableWidget::class,
            CostPeriodSummaryWidget::class,
        ];

        return array_values(array_filter($widgets, function (string $widget) use ($user): bool {
            return match ($widget) {
                PlantCostSummaryWidget::class => $user->hasPermission('costs.read') || $user->hasPermission('plants.read') || $user->hasRole('admin'),
                OpenAlertsWidget::class => $user->hasPermission('alerts.read') || $user->hasRole('admin'),
                PlantPerformanceTableWidget::class => $user->hasPermission('plants.read') || $user->hasRole('admin'),
                CostPeriodSummaryWidget::class => $user->hasPermission('costs.manage') || $user->hasPermission('costs.read') && $user->hasRole('plant_manager') || $user->hasRole('admin'),
                default => true,
            };
        }));
    }

    public function getViewData(): array
    {
        $user = auth()->user();
        $plants = Plant::query()->count();
        $openAlerts = Alert::query()->where('status', 'open')->count();
        $openIncidents = CostIncident::query()->where('status', '!=', 'closed')->count();

        $hasCostPeriodsTable = Schema::hasTable('cost_periods');
        $openPeriods = $hasCostPeriodsTable ? CostPeriod::query()->where('status', 'open')->count() : 0;
        $approvedPeriods = $hasCostPeriodsTable ? CostPeriod::query()->where('status', 'approved')->count() : 0;

        return [
            'userRole' => $user?->role ?? 'guest',
            'userRoleLabel' => str_replace('_', ' ', ucfirst((string) ($user?->role ?? 'guest'))),
            'plants' => $plants,
            'openAlerts' => $openAlerts,
            'openIncidents' => $openIncidents,
            'openPeriods' => $openPeriods,
            'approvedPeriods' => $approvedPeriods,
        ];
    }
}
