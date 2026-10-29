<?php

namespace App\Filament\Widgets;

use App\Domain\Organization\Models\Plant;
use App\Services\Costing\PlantCostSummaryService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PlantPerformanceTableWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $summaryService = new PlantCostSummaryService();
        $user = auth()->user();
        $query = Plant::query()->select('*');

        if (! $user?->hasRole('admin')) {
            $query->whereKey($user?->accessiblePlantIds() ?? []);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Plant')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->sortable(),
                Tables\Columns\TextColumn::make('costing_mode')
                    ->label('Cost model')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'batch' => 'info',
                        'daily' => 'warning',
                        'both' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('timezone')
                    ->label('Timezone')
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_currency')
                    ->label('Currency')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('costIncidents')
                    ->label('Open incidents')
                    ->state(fn (Plant $record) => $record->costIncidents()->where('status', '!=', 'closed')->count())
                    ->sortable(query: function ($query, $direction) {
                        return $query->withCount(['costIncidents as open_incidents_count' => fn ($query) => $query->where('status', '!=', 'closed')])
                            ->orderBy('open_incidents_count', $direction);
                    }),
                Tables\Columns\TextColumn::make('costIncidents')
                    ->label('Variance vs baseline')
                    ->state(function (Plant $record) use ($summaryService) {
                        $summary = $summaryService->forPlant($record);

                        return '$'.number_format((float) $summary['variance_to_baseline'], 2);
                    })
                    ->sortable(query: function ($query, $direction) {
                        return $query->withCount(['costIncidents as open_incidents_count' => fn ($query) => $query->where('status', '!=', 'closed')])
                            ->orderBy('open_incidents_count', $direction);
                    }),
            ])
            ->defaultSort('name')
            ->paginated([10]);
    }
}
