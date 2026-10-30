<?php

namespace App\Filament\Widgets;

use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Plant;
use App\Services\Costing\PlantCostSummaryService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlantCostSummaryWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $plantIds = $user?->hasRole('admin') ? null : ($user?->accessiblePlantIds() ?? collect());
        $summaries = (new PlantCostSummaryService)->forOrganization($user);

        $plantQuery = Plant::query();
        $incidentQuery = CostIncident::query()->where('status', '!=', 'closed');

        if ($plantIds !== null) {
            $plantQuery->whereKey($plantIds);
            $incidentQuery->whereIn('plant_id', $plantIds);
        }

        $plantCount = $plantQuery->count();
        $incidentCount = (clone $incidentQuery)->count();
        $openIncidentCost = (clone $incidentQuery)->sum('cash_cost');

        $varianceTotal = array_reduce($summaries, fn ($carry, $summary) => $carry + (float) ($summary['variance_to_baseline'] ?? 0), 0.0);
        $avgIncidents = $plantCount > 0 ? $incidentCount / $plantCount : 0;

        return [
            Stat::make('Plants online', (string) $plantCount)
                ->description('Active production sites')
                ->descriptionIcon('heroicon-o-building-office')
                ->color('success'),
            Stat::make('Open incidents', (string) $incidentCount)
                ->description('Unresolved cost events')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning'),
            Stat::make('Incident exposure', '$'.number_format((float) $openIncidentCost, 2))
                ->description('Unapproved cash impact')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('danger'),
            Stat::make('Variance vs baseline', '$'.number_format($varianceTotal, 2))
                ->description('Average '.number_format($avgIncidents, 1).' incidents per plant')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('primary'),
        ];
    }
}
