<?php

namespace App\Filament\Resources\Equipment;

use App\Filament\Resources\Equipment\Pages\CreateEquipment;
use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EquipmentResource extends Resource
{
    protected static ?string $model = Equipment::class;

    protected static ?string $navigationLabel = 'Equipment';

    protected static ?string $modelLabel = 'Equipment';

    protected static ?string $pluralModelLabel = 'Equipment';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory Management';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic details')
                    ->schema([
                        TextInput::make('equipment_code')
                            ->label('Equipment code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('property_number')
                            ->label('Property number')
                            ->maxLength(255),
                        TextInput::make('equipment_name')
                            ->label('Equipment name')
                            ->required()
                            ->maxLength(255),
                        Select::make('equipment_category_id')
                            ->label('Category')
                            ->options(fn (): array => EquipmentCategory::active()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Textarea::make('description')
                            ->columnSpanFull(),
                        TextInput::make('brand')
                            ->maxLength(255),
                        TextInput::make('model')
                            ->maxLength(255),
                        TextInput::make('serial_number')
                            ->label('Serial number')
                            ->maxLength(255),
                    ]),
                Section::make('Assignment and status')
                    ->schema([
                        Select::make('current_location_id')
                            ->label('Current location')
                            ->options(fn (): array => Location::active()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('custodian')
                            ->maxLength(255),
                        Select::make('condition')
                            ->options(Equipment::conditionOptions())
                            ->required()
                            ->rule(Rule::in(Equipment::CONDITIONS)),
                        Select::make('operational_status')
                            ->label('Operational status')
                            ->options(Equipment::operationalStatusOptions())
                            ->required()
                            ->rule(Rule::in(Equipment::OPERATIONAL_STATUSES)),
                        Toggle::make('is_archived')
                            ->label('Is archived')
                            ->required()
                            ->default(false)
                            ->disabled(fn (?Equipment $record): bool => $record !== null && Gate::denies('archive', $record))
                            ->dehydrated(fn (?Equipment $record): bool => $record === null || Gate::allows('archive', $record)),
                    ]),
                Section::make('Acquisition and maintenance')
                    ->schema([
                        DatePicker::make('acquisition_date')
                            ->label('Acquisition date'),
                        TextInput::make('acquisition_cost')
                            ->label('Acquisition cost')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('maintenance_frequency')
                            ->label('Maintenance frequency')
                            ->maxLength(255),
                        DatePicker::make('last_maintenance_date')
                            ->label('Last maintenance date'),
                        DatePicker::make('next_maintenance_date')
                            ->label('Next maintenance date'),
                        DatePicker::make('warranty_expiration_date')
                            ->label('Warranty expiration date'),
                        Textarea::make('remarks')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderBy('is_archived')->latest('created_at'))
            ->columns([
                TextColumn::make('equipment_code')
                    ->label('Equipment code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('property_number')
                    ->label('Property number')
                    ->searchable()
                    ->placeholder('None'),
                TextColumn::make('equipment_name')
                    ->label('Equipment name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category'),
                TextColumn::make('currentLocation.name')
                    ->label('Current location'),
                TextColumn::make('condition')
                    ->badge(),
                TextColumn::make('operational_status')
                    ->label('Operational status')
                    ->badge(),
                TextColumn::make('next_maintenance_date')
                    ->label('Next maintenance date')
                    ->date()
                    ->sortable(),
                TextColumn::make('is_archived')
                    ->label('Archived status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Archived' : 'Active'),
                TextColumn::make('created_at')
                    ->label('Created date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('equipment_category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                SelectFilter::make('current_location_id')
                    ->label('Current location')
                    ->relationship('currentLocation', 'name'),
                SelectFilter::make('condition')
                    ->options(Equipment::conditionOptions()),
                SelectFilter::make('operational_status')
                    ->label('Operational status')
                    ->options(Equipment::operationalStatusOptions()),
                TernaryFilter::make('is_archived')
                    ->label('Archived status'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->requiresConfirmation()
                    ->visible(fn (Equipment $record): bool => ! $record->is_archived && auth()->user()?->can('archive', $record))
                    ->action(function (Equipment $record): void {
                        $record->update([
                            'is_archived' => true,
                            'archived_at' => now(),
                            'archived_by' => auth()->id(),
                        ]);
                    }),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('viewAny', Equipment::class) ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', Equipment::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Equipment::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'equipment_code',
            'property_number',
            'equipment_name',
            'brand',
            'model',
            'serial_number',
            'custodian',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipment::route('/'),
            'create' => CreateEquipment::route('/create'),
            'view' => ViewEquipment::route('/{record}'),
            'edit' => EditEquipment::route('/{record}/edit'),
        ];
    }
}
