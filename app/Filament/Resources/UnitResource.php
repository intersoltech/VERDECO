<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\MasterData\Models\Unit;
use App\Filament\Resources\UnitResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UnitResource extends Resource
{
    // Units are global (tenant-scoped only, no plant_id) so we don't use RoleAwareResourceAccess
    // We implement canViewAny / canCreate manually.

    protected static ?string $model = Unit::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Units of Measure';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasPermission('units.read');
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasPermission('units.manage');
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasPermission('units.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Unit Details')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        ->label('Unit Code (e.g. kg, lb, kWh)')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(100)
                        ->label('Full Name (e.g. Kilogram)')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('symbol')
                        ->maxLength(20)
                        ->label('Symbol (e.g. kg, lb, kWh)')
                        ->columnSpan(1),

                    Forms\Components\Select::make('type')
                        ->options([
                            'mass' => 'Mass / Weight',
                            'volume' => 'Volume',
                            'energy' => 'Energy',
                            'currency' => 'Currency',
                            'time' => 'Time',
                            'other' => 'Other',
                        ])
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('conversion_to_base')
                        ->numeric()
                        ->default(1.0)
                        ->required()
                        ->label('Conversion to Base Unit (e.g. 1 lb = 0.45359237 kg → enter 0.45359237)')
                        ->helperText('The base unit for each type should have conversion_to_base = 1.0')
                        ->columnSpan(2),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label('Active')
                        ->columnSpan(1),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('symbol')
                    ->label('Symbol'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mass' => 'info',
                        'volume' => 'success',
                        'energy' => 'warning',
                        'currency' => 'danger',
                        'time' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('conversion_to_base')
                    ->label('Conv. to Base')
                    ->numeric(decimalPlaces: 8),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'mass' => 'Mass',
                        'volume' => 'Volume',
                        'energy' => 'Energy',
                        'currency' => 'Currency',
                        'time' => 'Time',
                        'other' => 'Other',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('type');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }
}
