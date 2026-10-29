<?php

namespace App\Filament\Resources;

use App\Domain\Production\Models\ProductionLine;
use App\Filament\Resources\ProductionLineResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductionLineResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = ProductionLine::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static function permissionKey(): string
    {
        return 'view-production-line';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('plant_id')
                    ->relationship('plant', 'name')
                    ->required(),
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('code')->required()->maxLength(255),
                Forms\Components\Select::make('type')->options([
                    'extruder' => 'Extruder',
                    'ssp' => 'SSP',
                    'packaging' => 'Packaging',
                ])->required(),
                Forms\Components\TextInput::make('rated_kg_per_hr')->numeric()->default(0),
                Forms\Components\TextInput::make('rated_kw')->numeric()->default(0),
                Forms\Components\Toggle::make('is_active')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('type')->sortable(),
                Tables\Columns\TextColumn::make('rated_kg_per_hr')->sortable(),
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
            'index' => Pages\ListProductionLines::route('/'),
            'create' => Pages\CreateProductionLine::route('/create'),
            'edit' => Pages\EditProductionLine::route('/{record}/edit'),
        ];
    }
}
