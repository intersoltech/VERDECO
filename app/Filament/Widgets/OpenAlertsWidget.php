<?php

namespace App\Filament\Widgets;

use App\Domain\Alerts\Models\Alert;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpenAlertsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $openAlerts = Alert::query()->where('status', 'open')->count();
        $highAlerts = Alert::query()->where('status', 'open')->where('severity', 'high')->count();
        $criticalAlerts = Alert::query()->where('status', 'open')->where('severity', 'critical')->count();

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
