<?php

namespace App\Filament\Resources;

use App\Domain\Alerts\Models\AlertRule;
use App\Filament\Resources\AlertRuleResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AlertRuleResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = AlertRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?string $navigationLabel = 'Alert rules';

    protected static ?string $recordTitleAttribute = 'name';

    protected static function permissionKey(): string
    {
        return 'view-alert-rule';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rule scope')
                    ->description('Apply the rule to a specific plant or let it roll up across the organization.')
                    ->schema([
                        Forms\Components\Select::make('plant_id')
                            ->label('Plant scope')
                            ->relationship('plant', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Leave empty for an organization-wide rule.'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Rule configuration')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Rule code')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('severity')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'critical' => 'Critical',
                            ])
                            ->default('medium')
                            ->required(),
                        Forms\Components\Toggle::make('enabled')->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Threshold logic')
                    ->description('Define the trigger condition and the message emitted when the rule fires.')
                    ->schema([
                        Forms\Components\TextInput::make('threshold_value')
                            ->label('Threshold value')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->required()
                            ->helperText('This is compared against the plant metric using the operator below.'),
                        Forms\Components\Select::make('comparison_operator')
                            ->label('Comparison')
                            ->options([
                                'gt' => 'Greater than',
                                'gte' => 'Greater than or equal',
                                'lt' => 'Less than',
                                'lte' => 'Less than or equal',
                                'eq' => 'Equal to',
                            ])
                            ->default('gt')
                            ->required(),
                        Forms\Components\TextInput::make('cooldown_minutes')
                            ->label('Cooldown (minutes)')
                            ->numeric()
                            ->default(60)
                            ->required(),
                        Forms\Components\Textarea::make('message_template')
                            ->label('Alert message template')
                            ->rows(3)
                            ->placeholder('{plant_code} has exceeded the threshold of {threshold}.')
                            ->columnSpanFull()
                            ->helperText('Available tokens: {plant_code}, {rule_name}, {threshold}, {value}'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.name')
                    ->label('Plant')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('severity')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'success',
                    }),
                Tables\Columns\IconColumn::make('enabled')->boolean(),
                Tables\Columns\TextColumn::make('threshold_value')
                    ->label('Threshold')
                    ->sortable()
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('comparison_operator')->sortable(),
                Tables\Columns\TextColumn::make('cooldown_minutes')
                    ->label('Cooldown')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('severity')->options([
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                    'critical' => 'Critical',
                ]),
                Tables\Filters\TernaryFilter::make('enabled'),
            ])
            ->actions([
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
            'index' => Pages\ListAlertRules::route('/'),
            'create' => Pages\CreateAlertRule::route('/create'),
            'edit' => Pages\EditAlertRule::route('/{record}/edit'),
        ];
    }
}
