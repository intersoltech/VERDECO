<?php

namespace App\Providers\Filament;

use App\Filament\Pages\OperationsDashboard;
use App\Filament\Resources\AlertResource;
use App\Filament\Resources\AlertRuleResource;
use App\Filament\Resources\BatchQualityResultResource;
use App\Filament\Resources\BudgetResource;
use App\Filament\Resources\CostCategoryResource;
use App\Filament\Resources\CostIncidentResource;
use App\Filament\Resources\CostPeriodResource;
use App\Filament\Resources\DailyProductionSummaryResource;
use App\Filament\Resources\DowntimeEventResource;
use App\Filament\Resources\MaterialResource;
use App\Filament\Resources\PlantResource;
use App\Filament\Resources\ProductGradeResource;
use App\Filament\Resources\ProductionBatchResource;
use App\Filament\Resources\ProductionLineResource;
use App\Filament\Resources\StandardCostResource;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\UnitResource;
use App\Filament\Widgets\OpenAlertsWidget;
use App\Filament\Widgets\PlantCostSummaryWidget;
use App\Filament\Widgets\PlantPerformanceTableWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->brandName('Verdeco')
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::Amber,
                'danger' => Color::Rose,
                'success' => Color::Emerald,
                'warning' => Color::Yellow,
                'info' => Color::Sky,
            ])
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->darkMode(true)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                OperationsDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                PlantCostSummaryWidget::class,
                OpenAlertsWidget::class,
                PlantPerformanceTableWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->resources([
                // Organization & Setup
                PlantResource::class,
                ProductionLineResource::class,
                SupplierResource::class,

                // Master Data
                MaterialResource::class,
                ProductGradeResource::class,
                UnitResource::class,
                CostCategoryResource::class,

                // Standards & Budgets
                StandardCostResource::class,
                BudgetResource::class,

                // Operations
                ProductionBatchResource::class,
                DowntimeEventResource::class,
                BatchQualityResultResource::class,
                DailyProductionSummaryResource::class,

                // Cost Management
                CostIncidentResource::class,
                CostPeriodResource::class,

                // Monitoring
                AlertResource::class,
                AlertRuleResource::class,
            ])
            ->navigationGroups([
                'Operations',
                'Master Data',
                'Standards & Budgets',
                'Cost Management',
                'Monitoring',
                'Administration',
            ]);
    }
}
