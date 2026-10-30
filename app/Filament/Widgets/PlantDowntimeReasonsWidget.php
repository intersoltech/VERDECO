<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\DowntimeEvent;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PlantDowntimeReasonsWidget extends BaseWidget
{
    public ?int $plantId = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Equipment Downtime & Top Root Causes (MTD)';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $plantQuery = Plant::query()->where('is_active', true);
        if (! $user?->hasRole('admin')) {
            $plantQuery->whereKey($user?->accessiblePlantIds() ?? []);
        }

        $plant = $this->plantId ? Plant::find($this->plantId) : $plantQuery->first();
        $startOfMonth = Carbon::now()->startOfMonth();

        $query = DowntimeEvent::query()
            ->with(['line'])
            ->whereDate('start_at', '>=', $startOfMonth);

        if ($plant) {
            $query->where('plant_id', $plant->id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('line.name')
                    ->label('Production Line')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('downtime_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'unplanned' ? 'danger' : 'info'),

                Tables\Columns\TextColumn::make('reason_code')
                    ->label('Reason Code')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duration (min)')
                    ->numeric()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('lost_production_kg')
                    ->label('Lost Output (kg)')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Event Description')
                    ->limit(50),

                Tables\Columns\TextColumn::make('start_at')
                    ->label('Start Time')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('start_at', 'desc')
            ->paginated([5, 10]);
    }
}
