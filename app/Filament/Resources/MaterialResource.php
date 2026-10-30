<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\MasterData\Models\Material;
use App\Filament\Resources\MaterialResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MaterialResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = Material::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Master data';

    protected static ?string $navigationLabel = 'Materials & Flake';

    protected static ?string $recordTitleAttribute = 'name';

    protected static function permissionArea(): string
    {
        return 'materials';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Material Definition')
                ->schema([
                    Forms\Components\Select::make('plant_id')
                        ->relationship('plant', 'name', modifyQueryUsing: fn ($query) => static::scopePlantOptions($query))
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('supplier_id')
                        ->relationship('supplier', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->label('Supplier'),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. Clear Hot-Washed PET Flake (Grade A)'),
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. FLK-CLR-A'),
                    Forms\Components\Select::make('category')
                        ->options([
                            'feedstock' => 'Feedstock (PET Flake)',
                            'additive' => 'Additive (IV Enhancer / Colorant)',
                            'packaging' => 'Packaging (Bulk Bags / Octabins / Liners)',
                            'consumable' => 'Consumable / Screen Packs',
                            'utility' => 'Utility (Nitrogen, Gas, Water)',
                        ])
                        ->default('feedstock')
                        ->required(),
                    Forms\Components\TextInput::make('unit')
                        ->default('kg')
                        ->required()
                        ->maxLength(20)
                        ->label('Unit of Measure (Internal Base: kg)'),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label('Active for production'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Effective-Dated Price History')
                ->schema([
                    Forms\Components\Repeater::make('prices')
                        ->relationship()
                        ->schema([
                            Forms\Components\TextInput::make('unit_price')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->label('Price per Unit ($)'),
                            Forms\Components\TextInput::make('currency_code')
                                ->default('USD')
                                ->maxLength(3)
                                ->label('Currency'),
                            Forms\Components\DateTimePicker::make('effective_from')
                                ->default(now())
                                ->required()
                                ->label('Effective From'),
                            Forms\Components\DateTimePicker::make('effective_to')
                                ->nullable()
                                ->label('Effective To'),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->addActionLabel('Add Price Tier'),
                ])
                ->visible(fn () => auth()->user()?->canViewFinancials() ?? false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.code')->label('Plant')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')->label('Supplier')->sortable(),
                Tables\Columns\TextColumn::make('category')->badge()->sortable(),
                Tables\Columns\TextColumn::make('unit')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'feedstock' => 'Feedstock',
                        'additive' => 'Additive',
                        'packaging' => 'Packaging',
                        'consumable' => 'Consumable',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}
