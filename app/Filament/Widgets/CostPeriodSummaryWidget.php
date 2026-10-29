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

        $openPeriods = CostPeriod::query()->where('status', 'open')->count();
        $approvedPeriods = CostPeriod::query()->where('status', 'approved')->count();
        $closedPeriods = CostPeriod::query()->where('status', 'closed')->count();
        $varianceTotal = (float) CostPeriod::query()->sum('variance_cost');

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
