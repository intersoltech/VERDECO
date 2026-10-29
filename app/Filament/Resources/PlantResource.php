<?php

namespace App\Filament\Resources;

use App\Domain\Organization\Models\Plant;
use App\Filament\Resources\PlantResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlantResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = Plant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Plants';

    protected static ?string $recordTitleAttribute = 'name';

    protected static function permissionArea(): string
    {
        return 'plants';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Plant profile')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('code')->required()->maxLength(255),
                        Forms\Components\TextInput::make('location')->maxLength(255),
                        Forms\Components\Select::make('timezone')->options([
                            'America/Chicago' => 'America/Chicago',
                            'America/New_York' => 'America/New_York',
                            'America/Phoenix' => 'America/Phoenix',
                            'UTC' => 'UTC',
                        ])->default('America/Chicago')->required(),
                        Forms\Components\Select::make('base_currency')->options([
                            'USD' => 'USD',
                            'EUR' => 'EUR',
                            'MXN' => 'MXN',
                        ])->default('USD')->required(),
                        Forms\Components\Select::make('costing_mode')->options([
                            'batch' => 'Batch',
                            'daily' => 'Daily',
                            'both' => 'Both',
                        ])->default('both')->required(),
                        Forms\Components\Toggle::make('is_active')->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('location')->sortable(),
                Tables\Columns\TextColumn::make('base_currency')->sortable(),
                Tables\Columns\TextColumn::make('costing_mode')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([])
            ->actions([
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
            'index' => Pages\ListPlants::route('/'),
            'create' => Pages\CreatePlant::route('/create'),
            'edit' => Pages\EditPlant::route('/{record}/edit'),
        ];
    }
}
