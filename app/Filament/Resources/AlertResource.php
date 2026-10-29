<?php

namespace App\Filament\Resources;

use App\Domain\Alerts\Models\Alert;
use App\Filament\Resources\AlertResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AlertResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = Alert::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?string $navigationLabel = 'Alerts';

    protected static ?string $recordTitleAttribute = 'rule';

    protected static function permissionArea(): string
    {
        return 'alerts';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('plant_id')
                    ->relationship('plant', 'name', modifyQueryUsing: fn ($query) => static::scopePlantOptions($query))
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('rule')->required()->maxLength(255),
                Forms\Components\Select::make('severity')->options([
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                    'critical' => 'Critical',
                ])->required(),
                Forms\Components\Select::make('status')->options([
                    'open' => 'Open',
                    'acknowledged' => 'Acknowledged',
                    'resolved' => 'Resolved',
                ])->required(),
                Forms\Components\Textarea::make('message')->rows(4)->required(),
                Forms\Components\DateTimePicker::make('triggered_at')->required(),
                Forms\Components\Select::make('acknowledged_by')
                    ->label('Acknowledged by')
                    ->relationship('acknowledgedBy', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\DateTimePicker::make('acknowledged_at')->nullable(),
                Forms\Components\Select::make('resolved_by')
                    ->label('Resolved by')
                    ->relationship('resolvedBy', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\DateTimePicker::make('resolved_at')->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('rule')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('severity')->sortable()->badge()->color(fn (string $state): string => match ($state) {
                    'critical' => 'danger',
                    'high' => 'warning',
                    'medium' => 'info',
                    default => 'success',
                }),
                Tables\Columns\TextColumn::make('status')->sortable()->badge()->color(fn (string $state): string => match ($state) {
                    'open' => 'warning',
                    'acknowledged' => 'info',
                    'resolved' => 'success',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('message')->limit(80)->wrap(),
                Tables\Columns\TextColumn::make('acknowledgedBy.name')->label('Acknowledged by')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('resolvedBy.name')->label('Resolved by')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('triggered_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('acknowledged_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('resolved_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('severity')->options([
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                    'critical' => 'Critical',
                ]),
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'acknowledged' => 'Acknowledged',
                    'resolved' => 'Resolved',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('acknowledge')
                    ->label('Acknowledge')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->visible(fn (Alert $record): bool => $record->status === 'open')
                    ->action(function (Alert $record): void {
                        $record->acknowledge(auth()->user());
                    }),
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Alert $record): bool => in_array($record->status, ['open', 'acknowledged'], true))
                    ->action(function (Alert $record): void {
                        $record->resolve(auth()->user());
                    }),
                Tables\Actions\Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Alert $record): bool => $record->status === 'resolved')
                    ->action(function (Alert $record): void {
                        $record->reopen(auth()->user());
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlerts::route('/'),
            'create' => Pages\CreateAlert::route('/create'),
            'edit' => Pages\EditAlert::route('/{record}/edit'),
        ];
    }
}
