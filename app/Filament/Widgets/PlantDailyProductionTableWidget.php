<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Costing\Models\DailyProductionSummary;
use App\Domain\Organization\Models\Plant;
use App\Services\Costing\DailyProductionCostingService;
use App\Services\Reporting\DashboardDisplayHelper;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PlantDailyProductionTableWidget extends BaseWidget
{
    public ?int $plantId = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Daily Production & Cost Accounting';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $plantQuery = Plant::query()->where('is_active', true);
        if (! $user?->hasRole('admin')) {
            $plantQuery->whereKey($user?->accessiblePlantIds() ?? []);
        }

        $plant = $this->plantId ? Plant::find($this->plantId) : $plantQuery->first();
        $displayUnit = DashboardDisplayHelper::currentUnit();

        $query = DailyProductionSummary::query()
            ->with(['batches', 'expenses'])
            ->where('plant_id', $plant?->id ?? 0)
            ->orderByDesc('production_date');

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('production_date')
                    ->label('Production Date')
                    ->date('M d, Y')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('batches_count')
                    ->label('Batches')
                    ->counts('batches')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('output_kg')
                    ->label('Good Output ('.strtoupper($displayUnit).')')
                    ->state(function (DailyProductionSummary $record) use ($displayUnit): string {
                        $val = DashboardDisplayHelper::convertWeight((float) $record->output_kg, $displayUnit);

                        return number_format($val, 0).' '.$displayUnit;
                    }),

                Tables\Columns\TextColumn::make('batch_total_cost')
                    ->label('Direct Batch Cost')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('daily_expense_total')
                    ->label('Daily Expenses')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('incident_total_cost')
                    ->label('Incident Cost')
                    ->money('USD')
                    ->color(fn (string $state): string => (float) $state > 0 ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('actual_total_cost')
                    ->label('Total Actual Cost')
                    ->money('USD')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cost_per_kg')
                    ->label('Cost / '.strtoupper($displayUnit))
                    ->state(function (DailyProductionSummary $record) use ($displayUnit): string {
                        $val = DashboardDisplayHelper::convertUnitCost((float) $record->cost_per_kg, $displayUnit);

                        return '$'.number_format($val, 4);
                    })
                    ->badge()
                    ->color(function (DailyProductionSummary $record) use ($displayUnit): string {
                        $val = DashboardDisplayHelper::convertUnitCost((float) $record->cost_per_kg, $displayUnit);
                        $target = DashboardDisplayHelper::convertUnitCost(0.95, $displayUnit);

                        return $val <= $target ? 'success' : 'danger';
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'calculated' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('recalculate')
                    ->label('Recalculate')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => auth()->user()?->hasPermission('costs.manage') ?? false)
                    ->action(function (DailyProductionSummary $record): void {
                        app(DailyProductionCostingService::class)->calculateAndSave($record);
                        Notification::make()->title('Daily production recalculated successfully')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No daily production summaries for this plant')
            ->emptyStateDescription('Daily production summaries aggregate direct batch runs, shift utilities, and day-level fixed overhead.');
    }
}
