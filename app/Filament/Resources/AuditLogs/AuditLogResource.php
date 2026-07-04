<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationLabel = 'Audit Trail';

    protected static ?string $modelLabel = 'Audit Log';

    protected static ?string $pluralModelLabel = 'Audit Trail';

    protected static string|\UnitEnum|null $navigationGroup = 'System Administration';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Summary')
                    ->schema([
                        TextEntry::make('summary')
                            ->label('Summary')
                            ->state(fn (AuditLog $record): string => $record->summary())
                            ->columnSpanFull(),
                        TextEntry::make('user.name')->label('User')->placeholder('System'),
                        TextEntry::make('action')->badge(),
                        TextEntry::make('module')->badge(),
                        TextEntry::make('created_at')->label('Timestamp')->dateTime(),
                    ]),
                Section::make('Entity')
                    ->schema([
                        TextEntry::make('entity_type')->label('Entity type')->placeholder('None'),
                        TextEntry::make('entity_id')->label('Entity ID')->placeholder('None'),
                        TextEntry::make('description')->columnSpanFull(),
                    ]),
                Section::make('Changes')
                    ->schema([
                        TextEntry::make('old_values')
                            ->label('Old values')
                            ->state(fn (AuditLog $record): string => self::formatJson($record->old_values))
                            ->columnSpanFull(),
                        TextEntry::make('new_values')
                            ->label('New values')
                            ->state(fn (AuditLog $record): string => self::formatJson($record->new_values))
                            ->columnSpanFull(),
                        TextEntry::make('metadata')
                            ->state(fn (AuditLog $record): string => self::formatJson($record->metadata))
                            ->columnSpanFull(),
                    ]),
                Section::make('Request')
                    ->schema([
                        TextEntry::make('ip_address')->label('IP address')->placeholder('None'),
                        TextEntry::make('method')->placeholder('None'),
                        TextEntry::make('request_id')->label('Request ID')->placeholder('None'),
                        TextEntry::make('url')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('user_agent')->label('User agent')->placeholder('None')->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user')->latest('created_at'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->orWhereHas('user', fn (Builder $query): Builder => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")))
                    ->sortable(),
                TextColumn::make('action')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('module')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('entity')
                    ->label('Entity')
                    ->state(fn (AuditLog $record): string => filled($record->entity_type)
                        ? class_basename($record->entity_type).($record->entity_id ? ' #'.$record->entity_id : '')
                        : 'None')
                    ->searchable(['entity_type', 'entity_id']),
                TextColumn::make('description')
                    ->searchable()
                    ->limit(80),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('Date from'),
                        DatePicker::make('created_until')->label('Date until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->dateRange($data['created_from'] ?? null, $data['created_until'] ?? null)),
                SelectFilter::make('user_id')
                    ->label('User')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('module')
                    ->options(fn (): array => AuditLog::query()->distinct()->orderBy('module')->pluck('module', 'module')->all())
                    ->searchable(),
                SelectFilter::make('action')
                    ->options(fn (): array => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all())
                    ->searchable(),
                SelectFilter::make('entity_type')
                    ->label('Entity type')
                    ->options(fn (): array => AuditLog::query()
                        ->whereNotNull('entity_type')
                        ->distinct()
                        ->orderBy('entity_type')
                        ->pluck('entity_type', 'entity_type')
                        ->mapWithKeys(fn (string $type): array => [$type => class_basename($type)])
                        ->all())
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('viewAny', AuditLog::class) ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', AuditLog::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }

    private static function formatJson(?array $values): string
    {
        if ($values === null || $values === []) {
            return 'None';
        }

        return json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'None';
    }
}
