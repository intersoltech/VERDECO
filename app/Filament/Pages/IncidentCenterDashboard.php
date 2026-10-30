<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\IncidentCostMetricsWidget;
use App\Filament\Widgets\IncidentParetoWidget;
use Filament\Pages\Page;

class IncidentCenterDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?string $navigationLabel = 'Incident Center';

    protected static ?string $title = 'Incident Center & Unplanned Cost Pareto';

    protected static ?string $slug = 'incident-center';

    protected static string $view = 'filament.pages.incident-center-dashboard';

    protected static ?int $navigationSort = 3;

    public function getHeaderWidgets(): array
    {
        return [
            IncidentCostMetricsWidget::class,
            IncidentParetoWidget::class,
        ];
    }
}
