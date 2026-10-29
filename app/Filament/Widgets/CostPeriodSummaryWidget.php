<?php

namespace App\Filament\Widgets;

use App\Domain\Costing\Models\CostPeriod;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class CostPeriodSummaryWidget extends BaseWidget
{
    protected function getStats(): array
    {
        if (! Schema::hasTable('cost_periods')) {
            return [
                Stat::make('Cost periods', 'Not available')
                    ->description('Database migration pending')
                    ->descriptionIcon('heroicon-o-exclamation-triangle')
                    ->color('warning'),
            ];
        }

        $user = auth()->user();
        $query = CostPeriod::query();

        if (! $user?->hasRole('admin')) {
            $query->whereIn('plant_id', $user?->accessiblePlantIds() ?? []);
        }

        $openPeriods = (clone $query)->where('status', 'open')->count();
        $approvedPeriods = (clone $query)->where('status', 'approved')->count();
        $closedPeriods = (clone $query)->where('status', 'closed')->count();
        $varianceTotal = (float) (clone $query)->sum('variance_cost');

        return [
            Stat::make('Open periods', (string) $openPeriods)
                ->description('Awaiting approval')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Approved', (string) $approvedPeriods)
                ->description('Ready for closeout')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('info'),
            Stat::make('Closed', (string) $closedPeriods)
                ->description('Finalized cost periods')
                ->descriptionIcon('heroicon-o-lock-closed')
                ->color('success'),
            Stat::make('Variance', '$'.number_format($varianceTotal, 2))
                ->description('Cumulative period variance')
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color('danger'),
        ];
    }
}
