<?php

namespace App\Filament\Resources;

use App\Domain\Costing\Models\CostPeriod;
use App\Filament\Resources\CostPeriodResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CostPeriodResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = CostPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Cost periods';

    protected static function permissionArea(): string
    {
        return 'cost-periods';
    }

    protected static function isRecordEditable(Model $record): bool
    {
        return $record->status === 'open';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Period details')
                    ->schema([
                        Forms\Components\Select::make('plant_id')
                            ->relationship('plant', 'name', modifyQueryUsing: fn ($query) => static::scopePlantOptions($query))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\DatePicker::make('period_start')->required(),
                        Forms\Components\DatePicker::make('period_end')->required(),
                        Forms\Components\TextInput::make('actual_cost')->numeric()->default(0)->required(),
                        Forms\Components\TextInput::make('standard_cost')->numeric()->default(0)->required(),
                        Forms\Components\TextInput::make('variance_cost')->numeric()->default(0)->required(),
                        Forms\Components\TextInput::make('output_kg')->numeric()->default(0)->required(),
                        Forms\Components\Textarea::make('comments')->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.name')->label('Plant')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('period_start')->date()->sortable(),
                Tables\Columns\TextColumn::make('period_end')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'open' => 'warning',
                    'approved' => 'info',
                    'closed' => 'success',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('actual_cost')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('standard_cost')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('variance_cost')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('output_kg')->label('Output kg')->sortable(),
                Tables\Columns\TextColumn::make('approvedBy.name')->label('Approved by')->toggleable(),
                Tables\Columns\TextColumn::make('closedBy.name')->label('Closed by')->toggleable(),
                Tables\Columns\TextColumn::make('reopenedBy.name')->label('Reopened by')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'approved' => 'Approved',
                    'closed' => 'Closed',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (CostPeriod $record): bool => $record->status === 'open' && auth()->user()?->hasPermission('cost-periods.manage'))
                    ->action(fn (CostPeriod $record) => $record->approve(auth()->user())),
                Tables\Actions\Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-lock-closed')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CostPeriod $record): bool => $record->status === 'approved' && auth()->user()?->hasPermission('cost-periods.manage'))
                    ->action(fn (CostPeriod $record) => $record->close(auth()->user())),
                Tables\Actions\Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (CostPeriod $record): bool => $record->status === 'closed' && auth()->user()?->hasPermission('cost-periods.manage'))
                    ->action(fn (CostPeriod $record) => $record->reopen(auth()->user())),
                Tables\Actions\Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->modalHeading('Cost period lifecycle history')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (CostPeriod $record) => view('filament.cost-period-history', [
                        'events' => $record->events()->get(),
                    ])),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCostPeriods::route('/'),
            'create' => Pages\CreateCostPeriod::route('/create'),
            'edit' => Pages\EditCostPeriod::route('/{record}/edit'),
        ];
    }
}
