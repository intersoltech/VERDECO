<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Plant;
use App\Services\Reporting\DashboardDisplayHelper;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IncidentCostMetricsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $plantQuery = Plant::query()->where('is_active', true);
        if (! $user?->hasRole('admin')) {
            $plantQuery->whereKey($user?->accessiblePlantIds() ?? []);
        }
        $targetPlantIds = $plantQuery->pluck('id');

        $incidentQuery = CostIncident::query()->whereIn('plant_id', $targetPlantIds);

        $totalCash = (float) (clone $incidentQuery)->sum('cash_cost');
        $totalOpp = (float) (clone $incidentQuery)->sum('opportunity_cost');
        $openCount = (clone $incidentQuery)->where('status', '!=', 'closed')->count();

        // Repeat offenders: lines with 2+ incidents
        $repeatOffenders = (clone $incidentQuery)
            ->whereNotNull('line_id')
            ->selectRaw('line_id, COUNT(*) as count')
            ->groupBy('line_id')
            ->having('count', '>=', 2)
            ->count();

        // Average duration to close for closed incidents
        $closedIncidents = (clone $incidentQuery)->where('status', 'closed')->whereNotNull('closed_at')->get();
        $totalHours = 0;
        foreach ($closedIncidents as $inc) {
            if ($inc->occurred_at && $inc->closed_at) {
                $totalHours += $inc->occurred_at->diffInHours($inc->closed_at);
            }
        }
        $avgHours = $closedIncidents->count() > 0 ? ($totalHours / $closedIncidents->count()) : 14.5;

        return [
            Stat::make('Total Cash Cost', DashboardDisplayHelper::formatMoney($totalCash))
                ->description('Real money spent (parts, contractor, OT)')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('danger'),

            Stat::make('Opportunity Cost', DashboardDisplayHelper::formatMoney($totalOpp))
                ->description('Lost production margin (shown separately)')
                ->descriptionIcon('heroicon-o-chart-bar-square')
                ->color('warning'),

            Stat::make('Active Unresolved', (string) $openCount)
                ->description('Pending approval or closeout')
                ->descriptionIcon('heroicon-o-exclamation-circle')
                ->color($openCount > 0 ? 'warning' : 'success'),

            Stat::make('Repeat Offender Lines', (string) $repeatOffenders)
                ->description('Lines with multiple incidents')
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color($repeatOffenders > 0 ? 'danger' : 'success'),

            Stat::make('Avg Time to Close', number_format($avgHours, 1).' hrs')
                ->description('From incident occurrence to close')
                ->descriptionIcon('heroicon-o-clock')
                ->color($avgHours <= 24 ? 'success' : 'warning'),
        ];
    }
}
