<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\MasterData\Models\ProductGrade;
use App\Filament\Resources\ProductGradeResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductGradeResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = ProductGrade::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Master data';

    protected static ?string $navigationLabel = 'Pellet Product Grades';

    protected static ?string $recordTitleAttribute = 'name';

    protected static function permissionArea(): string
    {
        return 'product-grades';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Grade Classification')
                ->schema([
                    Forms\Components\Select::make('plant_id')
                        ->relationship('plant', 'name', modifyQueryUsing: fn ($query) => static::scopePlantOptions($query))
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. Food Grade Bottle-to-Bottle rPET'),
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. R-PET-B2B-FG'),
                    Forms\Components\Select::make('grade_type')
                        ->options([
                            'food_grade' => 'Food Grade (FDA / EFSA Compliant)',
                            'bottle_to_bottle' => 'Bottle-to-Bottle High IV',
                            'thermoforming' => 'Thermoforming / Sheet Grade',
                            'custom_colored' => 'Custom Colored / Tinted',
                            'non_food' => 'Non-Food / Strapping & Fiber',
                            'downgrade' => 'Off-Spec / Downgrade',
                        ])
                        ->default('food_grade')
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('Quality Specification Thresholds')
                ->schema([
                    Forms\Components\TextInput::make('iv_min')
                        ->numeric()
                        ->label('Min Intrinsic Viscosity (IV dl/g)')
                        ->placeholder('0.8000'),
                    Forms\Components\TextInput::make('iv_max')
                        ->numeric()
                        ->label('Max Intrinsic Viscosity (IV dl/g)')
                        ->placeholder('0.8400'),
                    Forms\Components\TextInput::make('aa_max')
                        ->numeric()
                        ->label('Max Acetaldehyde (AA ppm)')
                        ->placeholder('1.0000'),
                    Forms\Components\TextInput::make('color_l_min')
                        ->numeric()
                        ->label('Min Color L* (Lightness)')
                        ->placeholder('65.0000'),
                    Forms\Components\TextInput::make('color_l_max')
                        ->numeric()
                        ->label('Max Color L* (Lightness)')
                        ->placeholder('75.0000'),
                    Forms\Components\TextInput::make('color_b_min')
                        ->numeric()
                        ->label('Min Color b* (Yellow/Blue Axis)')
                        ->placeholder('-1.0000'),
                    Forms\Components\TextInput::make('color_b_max')
                        ->numeric()
                        ->label('Max Color b* (Yellow/Blue Axis)')
                        ->placeholder('2.0000'),
                ])
                ->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.code')->label('Plant')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('grade_type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('iv_min')->label('IV Min')->numeric(decimalPlaces: 4)->sortable(),
                Tables\Columns\TextColumn::make('iv_max')->label('IV Max')->numeric(decimalPlaces: 4)->sortable(),
                Tables\Columns\TextColumn::make('aa_max')->label('AA Max (ppm)')->numeric(decimalPlaces: 4)->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('grade_type')
                    ->options([
                        'food_grade' => 'Food Grade',
                        'bottle_to_bottle' => 'Bottle-to-Bottle',
                        'thermoforming' => 'Thermoforming',
                        'non_food' => 'Non-Food',
                        'downgrade' => 'Downgrade',
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
            'index' => Pages\ListProductGrades::route('/'),
            'create' => Pages\CreateProductGrade::route('/create'),
            'edit' => Pages\EditProductGrade::route('/{record}/edit'),
        ];
    }
}
