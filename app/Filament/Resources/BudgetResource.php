<?php

namespace App\Filament\Resources;

use App\Domain\Costing\Models\Budget;
use App\Filament\Resources\BudgetResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BudgetResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = Budget::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Plant budgets';

    protected static function permissionArea(): string
    {
        return 'costs';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Budget specification')
                ->schema([
                    Forms\Components\Select::make('plant_id')
                        ->relationship('plant', 'name', modifyQueryUsing: fn ($query) => static::scopePlantOptions($query))
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\DatePicker::make('period_month')
                        ->label('Target Month')
                        ->format('Y-m-d')
                        ->displayFormat('F Y')
                        ->required(),
                    Forms\Components\TextInput::make('budgeted_cost')
                        ->label('Total Budgeted Cost ($)')
                        ->numeric()
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                            $cost = (float) $get('budgeted_cost');
                            $kg = (float) $get('budgeted_output_kg');
                            if ($kg > 0) {
                                $set('budgeted_cost_per_kg', round($cost / $kg, 6));
                            }
                        }),
                    Forms\Components\TextInput::make('budgeted_output_kg')
                        ->label('Target Good Output (kg)')
                        ->numeric()
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                            $cost = (float) $get('budgeted_cost');
                            $kg = (float) $get('budgeted_output_kg');
                            if ($kg > 0) {
                                $set('budgeted_cost_per_kg', round($cost / $kg, 6));
                            }
                        }),
                    Forms\Components\TextInput::make('budgeted_cost_per_kg')
                        ->label('Target Cost / kg ($/kg)')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(),
                    Forms\Components\Textarea::make('notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.name')->label('Plant')->sortable(),
                Tables\Columns\TextColumn::make('period_month')->date('F Y')->label('Month')->sortable(),
                Tables\Columns\TextColumn::make('budgeted_cost')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('budgeted_output_kg')->numeric(decimalPlaces: 2)->label('Target kg')->sortable(),
                Tables\Columns\TextColumn::make('budgeted_cost_per_kg')->label('Target $/kg')->numeric(decimalPlaces: 4)->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('period_month', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBudgets::route('/'),
            'create' => Pages\CreateBudget::route('/create'),
            'edit' => Pages\EditBudget::route('/{record}/edit'),
        ];
    }
}
