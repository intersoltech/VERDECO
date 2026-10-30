<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Costing\Models\CostCategory;
use App\Filament\Resources\CostCategoryResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CostCategoryResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = CostCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Cost Categories';

    protected static ?int $navigationSort = 20;

    protected static function permissionArea(): string
    {
        return 'cost-categories';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Category Details')
                ->schema([
                    Forms\Components\Select::make('plant_id')
                        ->relationship('plant', 'name', modifyQueryUsing: fn ($query) => static::scopePlantOptions($query))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->label('Plant')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(30)
                        ->unique(ignoreRecord: true)
                        ->label('Category Code (e.g. OH-ELEC)')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(120)
                        ->label('Category Name')
                        ->columnSpan(2),

                    Forms\Components\Select::make('kind')
                        ->options([
                            'direct' => 'Direct',
                            'indirect' => 'Indirect / Overhead',
                        ])
                        ->required()
                        ->label('Kind')
                        ->columnSpan(1),

                    Forms\Components\Select::make('type')
                        ->options([
                            'fixed' => 'Fixed',
                            'variable' => 'Variable',
                            'mixed' => 'Mixed (Semi-variable)',
                        ])
                        ->required()
                        ->label('Behavior')
                        ->columnSpan(1),

                    Forms\Components\Select::make('allocation_basis')
                        ->options([
                            'per_kg_output' => 'Per kg Good Output',
                            'per_kg_input' => 'Per kg Input',
                            'per_line_hour' => 'Per Line-Hour',
                            'per_batch' => 'Per Batch (Equal)',
                            'equal_per_day' => 'Equal Per Day',
                            'direct' => 'Direct (No Allocation)',
                        ])
                        ->required()
                        ->label('Allocation Basis')
                        ->helperText('How indirect costs in this category are spread across batches')
                        ->columnSpan(2),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label('Active')
                        ->columnSpan(1),
                ])
                ->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.code')
                    ->label('Plant')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('kind')
                    ->label('Kind')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'direct' => 'success',
                        'indirect' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Behavior')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fixed' => 'info',
                        'variable' => 'success',
                        'mixed' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('allocation_basis')
                    ->label('Allocation Basis')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'per_kg_output' => 'Per kg Output',
                        'per_kg_input' => 'Per kg Input',
                        'per_line_hour' => 'Per Line-Hour',
                        'per_batch' => 'Per Batch',
                        'equal_per_day' => 'Equal/Day',
                        'direct' => 'Direct',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('plant_id')
                    ->relationship('plant', 'name')
                    ->label('Plant'),

                Tables\Filters\SelectFilter::make('kind')
                    ->options([
                        'direct' => 'Direct',
                        'indirect' => 'Indirect',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'fixed' => 'Fixed',
                        'variable' => 'Variable',
                        'mixed' => 'Mixed',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCostCategories::route('/'),
            'create' => Pages\CreateCostCategory::route('/create'),
            'edit' => Pages\EditCostCategory::route('/{record}/edit'),
        ];
    }
}
