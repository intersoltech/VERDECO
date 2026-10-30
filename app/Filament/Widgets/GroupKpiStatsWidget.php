<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Costing\Models\Budget;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Plant;
use App\Domain\Reporting\Models\FactDailyPlantKpi;
use App\Domain\Reporting\Models\FactMonthlyVariance;
use App\Services\Reporting\DashboardDisplayHelper;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GroupKpiStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $user = auth()->user();
        $plantIds = $user?->hasRole('admin') ? null : ($user?->accessiblePlantIds() ?? collect());

        $plantQuery = Plant::query()->where('is_active', true);
        if ($plantIds !== null) {
            $plantQuery->whereKey($plantIds);
        }
        $targetPlantIds = $plantQuery->pluck('id');

        $currentMonth = Carbon::now()->startOfMonth()->toDateString();
        $daysInMonth = Carbon::now()->daysInMonth;
        $dayOfMonth = Carbon::now()->day;

        // Fact Monthly Variance totals
        $varianceQuery = FactMonthlyVariance::query()
            ->whereIn('plant_id', $targetPlantIds)
            ->where('period_month', $currentMonth);

        $totalActualCost = (float) (clone $varianceQuery)->sum('actual_cost');
        $totalStandardCost = (float) (clone $varianceQuery)->sum('standard_cost');
        $totalOutputKg = (float) (clone $varianceQuery)->sum('output_kg');

        // Budget for current month
        $totalBudgetCost = (float) Budget::query()
            ->whereIn('plant_id', $targetPlantIds)
            ->where('period_month', $currentMonth)
            ->sum('budgeted_cost');

        // Month-End Forecast: (actual cost / days elapsed) * days in month
        $forecastMonthEnd = $dayOfMonth > 0 ? ($totalActualCost / $dayOfMonth) * $daysInMonth : $totalActualCost;

        // Yield calculation across current month daily KPIs
        $kpiQuery = FactDailyPlantKpi::query()
            ->whereIn('plant_id', $targetPlantIds)
            ->whereDate('production_date', '>=', $currentMonth);

        $kpiInputKg = (float) (clone $kpiQuery)->sum('input_kg');
        $kpiOutputKg = (float) (clone $kpiQuery)->sum('good_output_kg');
        $avgYield = $kpiInputKg > 0 ? ($kpiOutputKg / $kpiInputKg) * 100 : 92.5;

        // Open incidents
        $openIncidents = CostIncident::query()
            ->whereIn('plant_id', $targetPlantIds)
            ->where('status', '!=', 'closed');
        $openIncidentCount = (clone $openIncidents)->count();
        $openIncidentCost = (float) (clone $openIncidents)->sum('cash_cost');

        // Units conversion
        $displayUnit = DashboardDisplayHelper::currentUnit();
        $displayOutput = DashboardDisplayHelper::convertWeight($totalOutputKg, $displayUnit);
        $costPerUnit = $totalOutputKg > 0 ? ($totalActualCost / $totalOutputKg) : 0.0;
        $displayUnitCost = DashboardDisplayHelper::convertUnitCost($costPerUnit, $displayUnit);

        $budgetVariance = $totalBudgetCost > 0 ? (($totalActualCost - $totalBudgetCost) / $totalBudgetCost) * 100 : 0.0;
        $budgetDesc = $totalBudgetCost > 0
            ? number_format(abs($budgetVariance), 1).'% '.($budgetVariance > 0 ? 'over budget' : 'under budget')
            : 'No budget set';

        return [
            Stat::make('Group Total Cost (MTD)', DashboardDisplayHelper::formatMoney($totalActualCost))
                ->description($budgetDesc)
                ->descriptionIcon($budgetVariance > 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($budgetVariance > 0 ? 'danger' : 'success'),

            Stat::make('Output MTD ('.strtoupper($displayUnit).')', number_format($displayOutput, 0).' '.$displayUnit)
                ->description('Avg Yield: '.number_format($avgYield, 1).'%')
                ->descriptionIcon('heroicon-o-scale')
                ->color($avgYield >= 92 ? 'success' : 'warning'),

            Stat::make('Group Cost / '.strtoupper($displayUnit), '$'.number_format($displayUnitCost, 4).' / '.$displayUnit)
                ->description('Standard: $'.number_format(DashboardDisplayHelper::convertUnitCost(0.95, $displayUnit), 4))
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color($displayUnitCost <= 0.95 ? 'success' : 'danger'),

            Stat::make('Month-End Forecast', DashboardDisplayHelper::formatMoney($forecastMonthEnd))
                ->description('Budget: '.DashboardDisplayHelper::formatMoney($totalBudgetCost))
                ->descriptionIcon('heroicon-o-calculator')
                ->color($forecastMonthEnd > $totalBudgetCost ? 'warning' : 'primary'),

            Stat::make('Incident Exposure', DashboardDisplayHelper::formatMoney($openIncidentCost))
                ->description($openIncidentCount.' active incident(s)')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($openIncidentCount > 0 ? 'danger' : 'success'),
        ];
    }
}
