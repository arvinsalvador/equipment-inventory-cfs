<?php

namespace App\Filament\Resources\EquipmentCategories;

use App\Filament\Resources\EquipmentCategories\Pages\CreateEquipmentCategory;
use App\Filament\Resources\EquipmentCategories\Pages\EditEquipmentCategory;
use App\Filament\Resources\EquipmentCategories\Pages\ListEquipmentCategories;
use App\Models\EquipmentCategory;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EquipmentCategoryResource extends Resource
{
    protected static ?string $model = EquipmentCategory::class;

    protected static ?string $navigationLabel = 'Equipment Categories';

    protected static ?string $modelLabel = 'Equipment Category';

    protected static ?string $pluralModelLabel = 'Equipment Categories';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('account_code')
                    ->label('Account Code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(20),
                TextInput::make('name')
                    ->label('Category Name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Active status')
                    ->required()
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account_code')
                    ->label('Account Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable()
                    ->limit(80),
                IconColumn::make('is_active')
                    ->label('Active status')
                    ->boolean(),
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
                TernaryFilter::make('is_active')
                    ->label('Active status'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('master-data.manage') ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('master-data.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', EquipmentCategory::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipmentCategories::route('/'),
            'create' => CreateEquipmentCategory::route('/create'),
            'edit' => EditEquipmentCategory::route('/{record}/edit'),
        ];
    }
}
