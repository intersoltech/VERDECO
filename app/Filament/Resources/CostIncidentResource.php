<?php

namespace App\Filament\Resources;

use App\Domain\Incidents\Models\CostIncident;
use App\Filament\Resources\CostIncidentResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CostIncidentResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = CostIncident::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Incidents';

    protected static function permissionArea(): string
    {
        return 'incidents';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Incident details')
                    ->schema([
                        Forms\Components\Select::make('plant_id')
                            ->relationship('plant', 'name', modifyQueryUsing: fn ($query) => static::scopePlantOptions($query))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('line_id')
                            ->relationship('line', 'name')
                            ->label('Line')
                            ->nullable(),
                        Forms\Components\Select::make('incident_type')->options([
                            'breakdown' => 'Breakdown',
                            'quality_hold' => 'Quality Hold',
                            'rework' => 'Rework',
                            'material_loss' => 'Material Loss',
                            'rush_freight' => 'Rush Freight',
                            'utility_spike' => 'Utility Spike',
                            'safety_incident' => 'Safety Incident',
                        ])->required(),
                        Forms\Components\Select::make('severity')->options([
                            'low' => 'Low',
                            'medium' => 'Medium',
                            'high' => 'High',
                            'critical' => 'Critical',
                        ])->required(),
                        Forms\Components\TextInput::make('root_cause_category')->required()->maxLength(255),
                        Forms\Components\TextInput::make('owner')->maxLength(255),
                        Forms\Components\Select::make('status')->options([
                            'open' => 'Open',
                            'approved' => 'Approved',
                            'closed' => 'Closed',
                        ])->default('open')->required(),
                        Forms\Components\DateTimePicker::make('occurred_at')->required(),
                        Forms\Components\TextInput::make('cash_cost')->numeric()->default(0),
                        Forms\Components\TextInput::make('opportunity_cost')->numeric()->default(0),
                        Forms\Components\Textarea::make('description')->rows(4)->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Incident Cost Breakdown Lines')
                    ->schema([
                        Forms\Components\Repeater::make('lines')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('line_type')->options([
                                    'extra_labor' => 'Extra Labor / Overtime',
                                    'emergency_maintenance' => 'Emergency Maintenance',
                                    'material_loss' => 'Material Loss / Contamination',
                                    'rework' => 'Rework / Reprocessing',
                                    'rush_freight' => 'Rush Freight / Expedite',
                                    'utility_spike' => 'Utility Spike',
                                    'quality_hold' => 'Quality Hold',
                                    'safety_incident' => 'Safety Incident',
                                    'other' => 'Other Incident Cost',
                                ])->required(),
                                Forms\Components\TextInput::make('description')->maxLength(255),
                                Forms\Components\TextInput::make('extra_labor_hours')->numeric()->default(0)->label('Labor Hours'),
                                Forms\Components\TextInput::make('extra_labor_rate')->numeric()->default(0)->label('Rate ($/hr)'),
                                Forms\Components\TextInput::make('overtime_multiplier')->numeric()->default(1.5)->label('OT Mult'),
                                Forms\Components\TextInput::make('headcount')->numeric()->default(1)->label('Headcount'),
                                Forms\Components\TextInput::make('parts_cost')->numeric()->default(0)->label('Parts Cost ($)'),
                                Forms\Components\TextInput::make('contractor_cost')->numeric()->default(0)->label('Contractor ($)'),
                                Forms\Components\TextInput::make('material_loss_kg')->numeric()->default(0)->label('Loss (kg)'),
                                Forms\Components\TextInput::make('material_loss_cost')->numeric()->default(0)->label('Loss Cost ($)'),
                                Forms\Components\TextInput::make('rework_cost')->numeric()->default(0)->label('Rework ($)'),
                                Forms\Components\TextInput::make('rush_freight_cost')->numeric()->default(0)->label('Rush Freight ($)'),
                                Forms\Components\TextInput::make('other_cost')->numeric()->default(0)->label('Other Cost ($)'),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Add Incident Cost Line'),
                    ]),
                Forms\Components\Section::make('Lifecycle audit')
                    ->schema([
                        Forms\Components\Select::make('approved_by')
                            ->relationship('approvedBy', 'name')
                            ->label('Approved by')
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('approved_at')->label('Approved at')->nullable(),
                        Forms\Components\Select::make('closed_by')
                            ->relationship('closedBy', 'name')
                            ->label('Closed by')
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('closed_at')->label('Closed at')->nullable(),
                        Forms\Components\Select::make('reopened_by')
                            ->relationship('reopenedBy', 'name')
                            ->label('Reopened by')
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('reopened_at')->label('Reopened at')->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.name')->label('Plant')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('incident_type')->label('Type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('severity')->sortable()->badge()->color(fn (string $state): string => match ($state) {
                    'critical' => 'danger',
                    'high' => 'warning',
                    'medium' => 'info',
                    default => 'success',
                }),
                Tables\Columns\TextColumn::make('status')->sortable()->badge()->color(fn (string $state): string => match ($state) {
                    'open' => 'warning',
                    'approved' => 'info',
                    'closed' => 'success',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('cash_cost')->label('Cash cost')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('opportunity_cost')->label('Opportunity cost')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('approved_by')->label('Approved by')->sortable()->searchable()->state(fn (CostIncident $record) => $record->approvedBy?->name),
                Tables\Columns\TextColumn::make('closed_by')->label('Closed by')->sortable()->searchable()->state(fn (CostIncident $record) => $record->closedBy?->name),
                Tables\Columns\TextColumn::make('occurred_at')->label('Occurred')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'approved' => 'Approved',
                    'closed' => 'Closed',
                ]),
                Tables\Filters\SelectFilter::make('severity')->options([
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                    'critical' => 'Critical',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn (CostIncident $record): bool => $record->status === 'open')
                    ->action(fn (CostIncident $record) => $record->approve(auth()->user())),
                Tables\Actions\Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-lock-closed')
                    ->color('success')
                    ->visible(fn (CostIncident $record): bool => in_array($record->status, ['open', 'approved'], true))
                    ->action(fn (CostIncident $record) => $record->close(auth()->user())),
                Tables\Actions\Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (CostIncident $record): bool => $record->status === 'closed')
                    ->action(fn (CostIncident $record) => $record->reopen(auth()->user())),
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
            'index' => Pages\ListCostIncidents::route('/'),
            'create' => Pages\CreateCostIncident::route('/create'),
            'edit' => Pages\EditCostIncident::route('/{record}/edit'),
        ];
    }
}
