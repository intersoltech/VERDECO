<?php

namespace App\Filament\Resources;

use App\Domain\Quality\Models\BatchQualityResult;
use App\Filament\Resources\BatchQualityResultResource\Pages;
use App\Filament\Support\RoleAwareResourceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BatchQualityResultResource extends Resource
{
    use RoleAwareResourceAccess;

    protected static ?string $model = BatchQualityResult::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Quality QC testing';

    protected static function permissionArea(): string
    {
        return 'production-batches';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('QC Sample Information')
                ->schema([
                    Forms\Components\Select::make('plant_id')
                        ->relationship('plant', 'name', modifyQueryUsing: fn ($query) => static::scopePlantOptions($query))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    Forms\Components\Select::make('production_batch_id')
                        ->relationship('batch', 'batch_number', modifyQueryUsing: function ($query, Forms\Get $get) {
                            $plantId = $get('plant_id');

                            return $plantId ? $query->where('plant_id', $plantId) : $query;
                        })
                        ->label('Production batch')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\DateTimePicker::make('tested_at')
                        ->default(now())
                        ->required(),
                    Forms\Components\TextInput::make('sample_identifier')
                        ->label('Sample Lot #')
                        ->maxLength(100),
                ])
                ->columns(2),

            Forms\Components\Section::make('Lab Quality Parameters (rPET Specs)')
                ->schema([
                    Forms\Components\TextInput::make('intrinsic_viscosity')
                        ->label('Intrinsic Viscosity [IV] (dl/g)')
                        ->numeric()
                        ->helperText('Standard: 0.76 - 0.82 dl/g')
                        ->required(),
                    Forms\Components\TextInput::make('acetaldehyde_ppm')
                        ->label('Acetaldehyde [AA] (ppm)')
                        ->numeric()
                        ->helperText('Max: 2.5 - 3.0 ppm')
                        ->required(),
                    Forms\Components\TextInput::make('color_l')
                        ->label('Color L* (Brightness)')
                        ->numeric()
                        ->helperText('Target: 80.0 - 100.0')
                        ->required(),
                    Forms\Components\TextInput::make('color_b')
                        ->label('Color b* (Yellowness)')
                        ->numeric()
                        ->helperText('Target: 0.2 - 2.5')
                        ->required(),
                    Forms\Components\TextInput::make('moisture_ppm')
                        ->label('Moisture (ppm)')
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('black_specks_count')
                        ->label('Contamination (Black Specks)')
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('fines_percent')
                        ->label('Fines / Dust %')
                        ->numeric()
                        ->default(0),
                ])
                ->columns(3),

            Forms\Components\Section::make('Quality Disposition')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options([
                            'pass' => 'Pass (Approved for Release)',
                            'hold' => 'Quality Hold (Pending Review)',
                            'quarantined' => 'Quarantined (Off-Spec)',
                            'fail' => 'Rejected / Scrap',
                        ])
                        ->default('pass')
                        ->required()
                        ->live(),
                    Forms\Components\TextInput::make('hold_reason')
                        ->label('Hold / Quarantine Reason')
                        ->visible(fn (Forms\Get $get): bool => in_array($get('status'), ['hold', 'quarantined', 'fail'], true)),
                    Forms\Components\Textarea::make('inspector_notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plant.code')->label('Plant')->sortable(),
                Tables\Columns\TextColumn::make('batch.batch_number')->label('Batch')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sample_identifier')->label('Sample ID')->searchable(),
                Tables\Columns\TextColumn::make('intrinsic_viscosity')->label('IV (dl/g)')->numeric(decimalPlaces: 4)->sortable(),
                Tables\Columns\TextColumn::make('acetaldehyde_ppm')->label('AA (ppm)')->numeric(decimalPlaces: 2)->sortable(),
                Tables\Columns\TextColumn::make('color_l')->label('L*')->numeric(decimalPlaces: 1)->sortable(),
                Tables\Columns\TextColumn::make('color_b')->label('b*')->numeric(decimalPlaces: 1)->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pass' => 'success',
                        'hold' => 'warning',
                        'quarantined', 'fail' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tested_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pass' => 'Pass',
                        'hold' => 'Hold',
                        'quarantined' => 'Quarantined',
                        'fail' => 'Fail',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('tested_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBatchQualityResults::route('/'),
            'create' => Pages\CreateBatchQualityResult::route('/create'),
            'edit' => Pages\EditBatchQualityResult::route('/{record}/edit'),
        ];
    }
}
