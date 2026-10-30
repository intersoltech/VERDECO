<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Organization\Models\Plant;
use App\Filament\Widgets\PlantCostBreakdownWidget;
use App\Filament\Widgets\PlantDailyProductionTableWidget;
use App\Filament\Widgets\PlantDowntimeReasonsWidget;
use App\Filament\Widgets\PlantOperationsKpiWidget;
use App\Filament\Widgets\PlantWaterfallWidget;
use App\Services\Reporting\DashboardDisplayHelper;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PlantViewDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?string $navigationLabel = 'Plant Operations View';

    protected static ?string $title = 'Plant Operations & Cost Waterfall';

    protected static ?string $slug = 'plant-view';

    protected static string $view = 'filament.pages.plant-view-dashboard';

    protected static ?int $navigationSort = 2;

    public ?int $selectedPlantId = null;

    public function mount(): void
    {
        $user = auth()->user();
        $query = Plant::query()->where('is_active', true);
        if (! $user?->hasRole('admin')) {
            $query->whereKey($user?->accessiblePlantIds() ?? []);
        }

        $sessionPlantId = session('active_plant_id');
        if ($sessionPlantId && $query->clone()->whereKey($sessionPlantId)->exists()) {
            $this->selectedPlantId = (int) $sessionPlantId;
        } else {
            $this->selectedPlantId = (int) $query->value('id');
            session(['active_plant_id' => $this->selectedPlantId]);
        }
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $query = Plant::query()->where('is_active', true);
        if (! $user?->hasRole('admin')) {
            $query->whereKey($user?->accessiblePlantIds() ?? []);
        }
        $plantOptions = $query->pluck('name', 'id')->all();

        $currentUnit = DashboardDisplayHelper::currentUnit();
        $otherUnit = $currentUnit === 'kg' ? 'lb' : 'kg';

        return [
            Action::make('selectPlant')
                ->label('Change Plant')
                ->icon('heroicon-o-map-pin')
                ->form([
                    Select::make('plant_id')
                        ->label('Select Plant')
                        ->options($plantOptions)
                        ->default($this->selectedPlantId)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->selectedPlantId = (int) $data['plant_id'];
                    session(['active_plant_id' => $this->selectedPlantId]);
                    Notification::make()->title('Plant switched')->success()->send();
                }),

            Action::make('toggleUnit')
                ->label('Display: '.strtoupper($currentUnit))
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->action(function () use ($otherUnit): void {
                    DashboardDisplayHelper::setUnit($otherUnit);
                    Notification::make()->title('Display unit switched to '.strtoupper($otherUnit))->success()->send();
                }),
        ];
    }

    public function getHeaderWidgets(): array
    {
        return [
            PlantOperationsKpiWidget::class,
            PlantDailyProductionTableWidget::class,
            PlantWaterfallWidget::class,
            PlantCostBreakdownWidget::class,
            PlantDowntimeReasonsWidget::class,
        ];
    }
}
