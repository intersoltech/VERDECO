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
            Forms\Components\Section::make('Live Cost & Yield Preview')
                ->schema([
                    Forms\Components\Placeholder::make('preview_yield')
                        ->label('Yield %')
                        ->content(function (Forms\Get $get): string {
                            $in = (float) $get('input_kg');
                            $out = (float) $get('good_output_kg');
                            if ($in <= 0) return '0.00 %';
                            return number_format(($out / $in) * 100, 2) . ' %';
                        }),
                    Forms\Components\Placeholder::make('preview_labor_cost')
                        ->label('Est. Labor Cost')
                        ->visible(fn () => auth()->user()?->canViewFinancials() ?? false)
                        ->content(function (Forms\Get $get): string {
                            $regH = (float) $get('labor_regular_hours');
                            $regR = (float) $get('labor_regular_rate');
                            $otH = (float) $get('labor_overtime_hours');
                            $otR = (float) $get('labor_overtime_rate');
                            $otM = (float) ($get('labor_overtime_multiplier') ?: 1.5);
                            $cost = ($regH * $regR) + ($otH * $otR * $otM);
                            return '$' . number_format($cost, 2);
                        }),
                    Forms\Components\Placeholder::make('preview_utility_cost')
                        ->label('Est. Utility Cost')
                        ->visible(fn () => auth()->user()?->canViewFinancials() ?? false)
                        ->content(function (Forms\Get $get): string {
                            $kwh = (float) $get('utility_kwh');
                            $rate = (float) $get('utility_rate');
                            return '$' . number_format($kwh * $rate, 2);
                        }),
                    Forms\Components\Placeholder::make('preview_cost_per_kg')
                        ->label('Est. Cost / kg')
                        ->visible(fn () => auth()->user()?->canViewFinancials() ?? false)
                        ->content(function (Forms\Get $get): string {
                            $out = (float) $get('good_output_kg');
                            if ($out <= 0) return '$0.000000 / kg';
                            $regH = (float) $get('labor_regular_hours');
                            $regR = (float) $get('labor_regular_rate');
                            $otH = (float) $get('labor_overtime_hours');
                            $otR = (float) $get('labor_overtime_rate');
                            $otM = (float) ($get('labor_overtime_multiplier') ?: 1.5);
                            $labor = ($regH * $regR) + ($otH * $otR * $otM);
                            $util = ((float) $get('utility_kwh')) * ((float) $get('utility_rate'));
                            $overhead = (float) $get('overhead_pool');
                            $incident = (float) $get('incident_cost');
                            $recovery = (float) $get('recovery_credit');
                            
                            $matTotal = 0;
                            $materials = (array) $get('materials');
                            foreach ($materials as $m) {
                                $matTotal += ((float) ($m['kg'] ?? 0)) * ((float) ($m['unit_price'] ?? 0));
                            }

                            $net = ($matTotal + $labor + $util + $overhead + $incident) - $recovery;
                            return '$' . number_format($net / $out, 6) . ' / kg';
                        }),
                    Forms\Components\Placeholder::make('preview_cost_per_lb')
                        ->label('Est. Cost / lb (US display)')
                        ->visible(fn () => auth()->user()?->canViewFinancials() ?? false)
                        ->content(function (Forms\Get $get): string {
                            $out = (float) $get('good_output_kg');
                            if ($out <= 0) return '$0.000000 / lb';
                            $regH = (float) $get('labor_regular_hours');
                            $regR = (float) $get('labor_regular_rate');
                            $otH = (float) $get('labor_overtime_hours');
                            $otR = (float) $get('labor_overtime_rate');
                            $otM = (float) ($get('labor_overtime_multiplier') ?: 1.5);
                            $labor = ($regH * $regR) + ($otH * $otR * $otM);
                            $util = ((float) $get('utility_kwh')) * ((float) $get('utility_rate'));
                            $overhead = (float) $get('overhead_pool');
                            $incident = (float) $get('incident_cost');
                            $recovery = (float) $get('recovery_credit');
                            
                            $matTotal = 0;
                            $materials = (array) $get('materials');
                            foreach ($materials as $m) {
                                $matTotal += ((float) ($m['kg'] ?? 0)) * ((float) ($m['unit_price'] ?? 0));
                            }

                            $net = ($matTotal + $labor + $util + $overhead + $incident) - $recovery;
                            $costPerKg = $net / $out;
                            return '$' . number_format($costPerKg * 0.45359237, 6) . ' / lb';
                        }),
                ])
                ->columns(5),
            Forms\Components\Section::make('Production inputs')
                ->schema([
                    Forms\Components\TextInput::make('input_kg')->numeric()->minValue(0)->default(0)->required()->live(onBlur: true),
                    Forms\Components\TextInput::make('good_output_kg')->numeric()->minValue(0)->default(0)->required()->live(onBlur: true),
                    Forms\Components\TextInput::make('recovery_credit')->numeric()->minValue(0)->default(0)->required()->visible(fn () => auth()->user()?->canViewFinancials() ?? false)->live(onBlur: true),
                    Forms\Components\TextInput::make('labor_regular_rate')->numeric()->minValue(0)->default(0)->required()->visible(fn () => auth()->user()?->canViewFinancials() ?? false)->live(onBlur: true),
                    Forms\Components\TextInput::make('labor_regular_hours')->numeric()->minValue(0)->default(0)->required()->live(onBlur: true),
                    Forms\Components\TextInput::make('labor_overtime_rate')->numeric()->minValue(0)->default(0)->required()->visible(fn () => auth()->user()?->canViewFinancials() ?? false)->live(onBlur: true),
                    Forms\Components\TextInput::make('labor_overtime_hours')->numeric()->minValue(0)->default(0)->required()->live(onBlur: true),
                    Forms\Components\TextInput::make('labor_overtime_multiplier')->numeric()->minValue(1)->default(1.5)->required()->visible(fn () => auth()->user()?->canViewFinancials() ?? false)->live(onBlur: true),
                    Forms\Components\TextInput::make('utility_kwh')->numeric()->minValue(0)->default(0)->required()->live(onBlur: true),
                    Forms\Components\TextInput::make('utility_rate')->numeric()->minValue(0)->default(0)->required()->visible(fn () => auth()->user()?->canViewFinancials() ?? false)->live(onBlur: true),
                    Forms\Components\TextInput::make('overhead_pool')->numeric()->minValue(0)->default(0)->required()->visible(fn () => auth()->user()?->canViewFinancials() ?? false)->live(onBlur: true),
                    Forms\Components\Select::make('overhead_basis')->options([
                        'per_kg' => 'Per kg',
                        'per_hour' => 'Per hour',
                        'equal' => 'Equal allocation',
                    ])->default('per_kg')->required()->visible(fn () => auth()->user()?->canViewFinancials() ?? false),
                    Forms\Components\TextInput::make('incident_cost')->numeric()->minValue(0)->default(0)->required()->visible(fn () => auth()->user()?->canViewFinancials() ?? false)->live(onBlur: true),
                    Forms\Components\Select::make('incident_allocation_rule')->options([
                        'direct_tag' => 'Direct tag',
                        'by_kg' => 'By kg',
                        'equal' => 'Equal allocation',
                    ])->default('direct_tag')->required()->visible(fn () => auth()->user()?->canViewFinancials() ?? false),
                ])
                ->columns(3),
            Forms\Components\Section::make('Material consumption')
                ->schema([
                    Forms\Components\Repeater::make('materials')
                        ->relationship()
                        ->schema([
                            Forms\Components\TextInput::make('material_name')->required()->maxLength(255),
                            Forms\Components\TextInput::make('kg')->numeric()->minValue(0)->required(),
                            Forms\Components\TextInput::make('unit_price')->numeric()->minValue(0)->default(0)->visible(fn () => auth()->user()?->canViewFinancials() ?? false),
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
                Tables\Columns\TextColumn::make('net_cost')->money('USD')->sortable()->visible(fn () => auth()->user()?->canViewFinancials() ?? false),
                Tables\Columns\TextColumn::make('cost_per_kg')->label('Cost / kg')->numeric(decimalPlaces: 6)->sortable()->visible(fn () => auth()->user()?->canViewFinancials() ?? false),
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