<?php

namespace App\Filament\Resources;

use App\Domain\Production\Models\ProductionBatch;
use App\Filament\Resources\ProductionBatchResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use App\Services\Costing\ProductionBatchCostingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductionBatchResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = ProductionBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Production batches';

    protected static ?string $recordTitleAttribute = 'batch_number';

    protected static function permissionArea(): string
    {
        return 'production-batches';
    }

    protected static function isRecordEditable(Model $record): bool
    {
        return $record->status === 'draft';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Batch identity')
                ->schema([
                    Forms\Components\Select::make('plant_id')
                        ->relationship('plant', 'name', modifyQueryUsing: fn ($query) => static::scopePlantOptions($query))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabledOn('edit'),
                    Forms\Components\TextInput::make('batch_number')->required()->maxLength(100),
                    Forms\Components\DatePicker::make('batch_date')->required(),
                ])
                ->columns(3),
            Forms\Components\Section::make('Production inputs')
                ->schema([
                    Forms\Components\TextInput::make('input_kg')->numeric()->minValue(0)->default(0)->required(),
                    Forms\Components\TextInput::make('good_output_kg')->numeric()->minValue(0)->default(0)->required(),
                    Forms\Components\TextInput::make('recovery_credit')->numeric()->minValue(0)->default(0)->required(),
                    Forms\Components\TextInput::make('labor_regular_rate')->numeric()->minValue(0)->default(0)->required(),
                    Forms\Components\TextInput::make('labor_regular_hours')->numeric()->minValue(0)->default(0)->required(),
                    Forms\Components\TextInput::make('labor_overtime_rate')->numeric()->minValue(0)->default(0)->required(),
                    Forms\Components\TextInput::make('labor_overtime_hours')->numeric()->minValue(0)->default(0)->required(),
                    Forms\Components\TextInput::make('labor_overtime_multiplier')->numeric()->minValue(1)->default(1.5)->required(),
                    Forms\Components\TextInput::make('utility_kwh')->numeric()->minValue(0)->default(0)->required(),
                    Forms\Components\TextInput::make('utility_rate')->numeric()->minValue(0)->default(0)->required(),
                    Forms\Components\TextInput::make('overhead_pool')->numeric()->minValue(0)->default(0)->required(),
                    Forms\Components\Select::make('overhead_basis')->options([
                        'per_kg' => 'Per kg',
                        'per_hour' => 'Per hour',
                        'equal' => 'Equal allocation',
                    ])->default('per_kg')->required(),
                    Forms\Components\TextInput::make('incident_cost')->numeric()->minValue(0)->default(0)->required(),
                    Forms\Components\Select::make('incident_allocation_rule')->options([
                        'direct_tag' => 'Direct tag',
                        'by_kg' => 'By kg',
                        'equal' => 'Equal allocation',
                    ])->default('direct_tag')->required(),
                ])
                ->columns(3),
            Forms\Components\Section::make('Material consumption')
                ->schema([
                    Forms\Components\Repeater::make('materials')
                        ->relationship()
                        ->schema([
                            Forms\Components\TextInput::make('material_name')->required()->maxLength(255),
                            Forms\Components\TextInput::make('kg')->numeric()->minValue(0)->required(),
                            Forms\Components\TextInput::make('unit_price')->numeric()->minValue(0)->required(),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->addActionLabel('Add material'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('batch_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('plant.code')->label('Plant')->sortable(),
                Tables\Columns\TextColumn::make('batch_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => $state === 'costed' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('input_kg')->numeric(decimalPlaces: 2)->sortable(),
                Tables\Columns\TextColumn::make('good_output_kg')->numeric(decimalPlaces: 2)->sortable(),
                Tables\Columns\TextColumn::make('net_cost')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('cost_per_kg')->label('Cost / kg')->numeric(decimalPlaces: 6)->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('calculate_cost')
                    ->label('Calculate cost')
                    ->icon('heroicon-o-calculator')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ProductionBatch $record): bool => $record->status === 'draft' && auth()->user()?->hasPermission('production-batches.manage'))
                    ->action(fn (ProductionBatch $record) => app(ProductionBatchCostingService::class)->calculateAndSave($record)),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('batch_date', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductionBatches::route('/'),
            'create' => Pages\CreateProductionBatch::route('/create'),
            'edit' => Pages\EditProductionBatch::route('/{record}/edit'),
        ];
    }
}