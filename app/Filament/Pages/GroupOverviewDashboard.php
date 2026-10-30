<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\GroupBenchmarkTableWidget;
use App\Filament\Widgets\GroupKpiStatsWidget;
use App\Filament\Widgets\TopVarianceDriversWidget;
use App\Services\Reporting\DashboardDisplayHelper;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GroupOverviewDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?string $navigationLabel = 'Group Overview';

    protected static ?string $title = 'Group Executive Cost & Performance';

    protected static ?string $slug = 'group-overview';

    protected static string $view = 'filament.pages.group-overview-dashboard';

    protected static ?int $navigationSort = 1;

    protected function getHeaderActions(): array
    {
        $currentUnit = DashboardDisplayHelper::currentUnit();
        $otherUnit = $currentUnit === 'kg' ? 'lb' : 'kg';

        return [
            Action::make('toggleUnit')
                ->label('Display: '.strtoupper($currentUnit).' (Switch to '.strtoupper($otherUnit).')')
                ->icon('heroicon-o-arrows-right-left')
                ->color('primary')
                ->action(function () use ($otherUnit): void {
                    DashboardDisplayHelper::setUnit($otherUnit);
                    Notification::make()
                        ->title('Display unit switched to '.strtoupper($otherUnit))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getHeaderWidgets(): array
    {
        return [
            GroupKpiStatsWidget::class,
            GroupBenchmarkTableWidget::class,
            TopVarianceDriversWidget::class,
        ];
    }
}
