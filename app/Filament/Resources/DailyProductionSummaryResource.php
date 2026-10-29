<?php

namespace App\Filament\Resources;

use App\Domain\Costing\Models\DailyProductionSummary;
use App\Filament\Resources\DailyProductionSummaryResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use App\Services\Costing\DailyProductionCostingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DailyProductionSummaryResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = DailyProductionSummary::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Daily production';

    protected static ?string $recordTitleAttribute = 'production_date';

    protected static function permissionArea(): string
    {
        return 'costs';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Production day')
                ->schema([
                    Forms\Components\Select::make('plant_id')
                        ->relationship('plant', 'name', modifyQueryUsing: fn ($query) => static::scopePlantOptions($query))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabledOn('edit'),
                    Forms\Components\DatePicker::make('production_date')->required()->disabledOn('edit'),
                ])
                ->columns(2),
            Forms\Components\Section::make('Daily expenses')
                ->schema([
                    Forms\Components\Repeater::make('expenses')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('category')->options([
                                'utilities' => 'Utilities',
                                'labor' => 'Labor',
                                'supplies' => 'Supplies',
                                'maintenance' => 'Maintenance',
                                'other' => 'Other',
                            ])->required(),
                            Forms\Components\TextInput::make('description')->maxLength(255),
                            Forms\Components\TextInput::make('amount')->numeric()->minValue(0)->required(),
                            Forms\Components\Select::make('currency_code')->options([
                                'USD' => 'USD',
                                'EUR' => 'EUR',
                                'MXN' => 'MXN',
                            ])->default('USD')->required(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Add daily expense'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.code')->label('Plant')->sortable(),
                Tables\Columns\TextColumn::make('production_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => $state === 'calculated' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('batch_total_cost')->label('Batch cost')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('daily_expense_total')->label('Daily expenses')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('incident_total_cost')->label('Incident cost')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('actual_total_cost')->label('Actual cost')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('output_kg')->label('Output kg')->numeric(decimalPlaces: 2)->sortable(),
                Tables\Columns\TextColumn::make('cost_per_kg')->label('Cost / kg')->numeric(decimalPlaces: 6)->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('calculate')
                    ->label('Calculate day')
                    ->icon('heroicon-o-calculator')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (DailyProductionSummary $record): bool => in_array($record->status, ['draft', 'calculated'], true) && auth()->user()?->hasPermission('costs.manage'))
                    ->action(fn (DailyProductionSummary $record) => app(DailyProductionCostingService::class)->calculateAndSave($record)),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('production_date', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailyProductionSummaries::route('/'),
            'create' => Pages\CreateDailyProductionSummary::route('/create'),
            'edit' => Pages\EditDailyProductionSummary::route('/{record}/edit'),
        ];
    }
}