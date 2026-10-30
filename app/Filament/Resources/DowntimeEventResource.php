<?php

namespace App\Filament\Resources;

use App\Domain\Production\Models\DowntimeEvent;
use App\Filament\Resources\DowntimeEventResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DowntimeEventResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = DowntimeEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-pause-circle';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Downtime events';

    protected static function permissionArea(): string
    {
        return 'production-lines';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Downtime details')
                ->schema([
                    Forms\Components\Select::make('plant_id')
                        ->relationship('plant', 'name', modifyQueryUsing: fn ($query) => static::scopePlantOptions($query))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    Forms\Components\Select::make('line_id')
                        ->relationship('line', 'name', modifyQueryUsing: function ($query, Forms\Get $get) {
                            $plantId = $get('plant_id');
                            return $plantId ? $query->where('plant_id', $plantId) : $query;
                        })
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('downtime_type')
                        ->options([
                            'unplanned' => 'Unplanned Breakdown',
                            'planned' => 'Planned Maintenance',
                        ])
                        ->default('unplanned')
                        ->required(),
                    Forms\Components\Select::make('reason_code')
                        ->options([
                            'screen_pack_change' => 'Screen Pack Change',
                            'die_cleaning' => 'Die Cleaning',
                            'mechanical_failure' => 'Mechanical Failure',
                            'electrical_outage' => 'Electrical / Sensor Outage',
                            'feed_starvation' => 'Feed Starvation',
                            'maintenance_screen_cleaning' => 'Scheduled Maintenance',
                            'changeover' => 'Product Grade Changeover',
                            'other' => 'Other',
                        ])
                        ->required(),
                    Forms\Components\DateTimePicker::make('start_at')
                        ->required()
                        ->live(onBlur: true),
                    Forms\Components\DateTimePicker::make('end_at')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                            $start = $get('start_at');
                            $end = $get('end_at');
                            if ($start && $end) {
                                $diff = \Carbon\Carbon::parse($start)->diffInMinutes(\Carbon\Carbon::parse($end));
                                $set('duration_minutes', (int) $diff);
                            }
                        }),
                    Forms\Components\TextInput::make('duration_minutes')
                        ->numeric()
                        ->default(0)
                        ->required(),
                    Forms\Components\TextInput::make('lost_production_kg')
                        ->numeric()
                        ->label('Lost production (kg)')
                        ->default(0),
                    Forms\Components\Select::make('cost_incident_id')
                        ->relationship('costIncident', 'description')
                        ->label('Linked Incident')
                        ->searchable()
                        ->nullable(),
                    Forms\Components\Textarea::make('description')
                        ->label('Root Cause & Notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.code')->label('Plant')->sortable(),
                Tables\Columns\TextColumn::make('line.name')->label('Line')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('downtime_type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'unplanned' ? 'danger' : 'info'),
                Tables\Columns\TextColumn::make('reason_code')->label('Reason')->sortable(),
                Tables\Columns\TextColumn::make('start_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('duration_minutes')->label('Duration (min)')->sortable(),
                Tables\Columns\TextColumn::make('lost_production_kg')->label('Lost output (kg)')->numeric(decimalPlaces: 2)->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('downtime_type')
                    ->options([
                        'unplanned' => 'Unplanned',
                        'planned' => 'Planned',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('start_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDowntimeEvents::route('/'),
            'create' => Pages\CreateDowntimeEvent::route('/create'),
            'edit' => Pages\EditDowntimeEvent::route('/{record}/edit'),
        ];
    }
}
