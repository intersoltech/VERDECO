<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Costing\Models\StandardCost;
use App\Filament\Resources\StandardCostResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StandardCostResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = StandardCost::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Standards & Budgets';

    protected static ?string $navigationLabel = 'Standard Costs';

    protected static function permissionArea(): string
    {
        return 'standard-costs';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Scope & Effective Period')
                ->schema([
                    Forms\Components\Select::make('plant_id')
                        ->relationship('plant', 'name', modifyQueryUsing: fn ($query) => static::scopePlantOptions($query))
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('line_id')
                        ->relationship('line', 'name')
                        ->label('Production Line (Optional / All Lines)')
                        ->nullable(),
                    Forms\Components\Select::make('product_grade_id')
                        ->relationship('productGrade', 'name')
                        ->label('Product Grade')
                        ->required()
                        ->searchable(),
                    Forms\Components\DateTimePicker::make('effective_from')
                        ->default(now()->startOfYear())
                        ->required(),
                    Forms\Components\DateTimePicker::make('effective_to')
                        ->nullable(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'pending_approval' => 'Pending Approval',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                        ])
                        ->default('approved')
                        ->required(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Standard Cost Drivers & Engineering Norms')
                ->schema([
                    Forms\Components\TextInput::make('standard_yield_percent')
                        ->numeric()
                        ->default(94.5000)
                        ->required()
                        ->label('Standard Yield % (Good Output / Input)'),
                    Forms\Components\TextInput::make('kg_per_hour')
                        ->numeric()
                        ->default(1800.0000)
                        ->required()
                        ->label('Rated Throughput (kg / hr)'),
                    Forms\Components\TextInput::make('kwh_per_kg')
                        ->numeric()
                        ->default(0.380000)
                        ->required()
                        ->label('Energy Intensity Norm (kWh / kg output)'),
                    Forms\Components\TextInput::make('labor_hours_per_ton')
                        ->numeric()
                        ->default(3.200000)
                        ->required()
                        ->label('Direct Labor Norm (Hours / Ton output)'),
                    Forms\Components\TextInput::make('material_usage_kg_per_kg')
                        ->numeric()
                        ->default(1.058200)
                        ->required()
                        ->label('Material Usage Norm (kg input / kg output)'),
                    Forms\Components\TextInput::make('overhead_rate')
                        ->numeric()
                        ->default(0.120000)
                        ->required()
                        ->label('Standard Overhead Rate ($ / kg)'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.code')->label('Plant')->sortable(),
                Tables\Columns\TextColumn::make('productGrade.name')->label('Grade')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('line.name')->label('Line')->default('All Lines')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'approved' => 'success',
                    'pending_approval' => 'warning',
                    'rejected' => 'danger',
                    default => 'gray',
                })->sortable(),
                Tables\Columns\TextColumn::make('standard_yield_percent')->label('Yield %')->numeric(decimalPlaces: 2)->sortable(),
                Tables\Columns\TextColumn::make('kg_per_hour')->label('kg/hr')->numeric(decimalPlaces: 0)->sortable(),
                Tables\Columns\TextColumn::make('kwh_per_kg')->label('kWh/kg')->numeric(decimalPlaces: 4)->sortable(),
                Tables\Columns\TextColumn::make('labor_hours_per_ton')->label('Hrs/Ton')->numeric(decimalPlaces: 2)->sortable(),
                Tables\Columns\TextColumn::make('effective_from')->dateTime('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('effective_to')->dateTime('Y-m-d')->placeholder('Current')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('effective_from', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStandardCosts::route('/'),
            'create' => Pages\CreateStandardCost::route('/create'),
            'edit' => Pages\EditStandardCost::route('/{record}/edit'),
        ];
    }
}
