<?php

namespace App\Filament\Widgets;

use App\Domain\Alerts\Models\Alert;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpenAlertsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $query = Alert::query()->where('status', 'open');

        if (! $user?->hasRole('admin')) {
            $query->whereIn('plant_id', $user?->accessiblePlantIds() ?? []);
        }

        $openAlerts = (clone $query)->count();
        $highAlerts = (clone $query)->where('severity', 'high')->count();
        $criticalAlerts = (clone $query)->where('severity', 'critical')->count();

        return [
            Stat::make('Open alerts', (string) $openAlerts)
                ->description('Current unresolved alerts')
                ->descriptionIcon('heroicon-o-bell-alert')
                ->color('warning'),
            Stat::make('High severity', (string) $highAlerts)
                ->description('Action required this shift')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger'),
            Stat::make('Critical', (string) $criticalAlerts)
                ->description('Escalated production risk')
                ->descriptionIcon('heroicon-o-shield-exclamation')
                ->color('danger'),
        ];
    }
}
