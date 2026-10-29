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
        $plantIds = $user?->hasRole('admin') ? null : ($user?->accessiblePlantIds() ?? collect());

        $plantQuery = Plant::query();
        $alertQuery = Alert::query()->where('status', 'open');
        $incidentQuery = CostIncident::query()->where('status', '!=', 'closed');

        if ($plantIds !== null) {
            $plantQuery->whereKey($plantIds);
            $alertQuery->whereIn('plant_id', $plantIds);
            $incidentQuery->whereIn('plant_id', $plantIds);
        }

        $plants = $plantQuery->count();
        $openAlerts = $alertQuery->count();
        $openIncidents = $incidentQuery->count();

        $hasCostPeriodsTable = Schema::hasTable('cost_periods');
        $periodQuery = CostPeriod::query();
        if ($plantIds !== null) {
            $periodQuery->whereIn('plant_id', $plantIds);
        }

        $openPeriods = $hasCostPeriodsTable ? (clone $periodQuery)->where('status', 'open')->count() : 0;
        $approvedPeriods = $hasCostPeriodsTable ? (clone $periodQuery)->where('status', 'approved')->count() : 0;

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
