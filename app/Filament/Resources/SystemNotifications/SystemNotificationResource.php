<?php

namespace App\Filament\Resources\SystemNotifications;

use App\Filament\Resources\SystemNotifications\Pages\ListSystemNotifications;
use App\Models\SystemNotification;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemNotificationResource extends Resource
{
    protected static ?string $model = SystemNotification::class;

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?string $modelLabel = 'Notification';

    protected static ?string $pluralModelLabel = 'Notifications';

    protected static string|\UnitEnum|null $navigationGroup = 'System Management';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->visibleTo(auth()->user())
                ->active()
                ->orderByRaw('case when read_at is null then 0 else 1 end')
                ->orderByRaw("case priority when 'Critical' then 1 when 'High' then 2 when 'Normal' then 3 when 'Low' then 4 else 5 end")
                ->latest('generated_at'))
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SystemNotification $record): string => str($record->message)->limit(120)->toString()),
                TextColumn::make('priority')
                    ->badge()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->sortable(),
                TextColumn::make('notification_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('Global')
                    ->toggleable(),
                TextColumn::make('read_at')
                    ->label('Read')
                    ->state(fn (SystemNotification $record): string => $record->isRead() ? 'Read' : 'Unread')
                    ->badge(),
                TextColumn::make('email_status')
                    ->label('Email')
                    ->state(fn (SystemNotification $record): string => match (true) {
                        $record->wasEmailed() => 'Sent',
                        $record->emailFailed() => 'Failed',
                        $record->email_delivery_attempts > 0 => 'Attempted',
                        default => 'Not sent',
                    })
                    ->badge()
                    ->toggleable(),
                TextColumn::make('email_delivery_attempts')
                    ->label('Email attempts')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('generated_at')
                    ->label('Generated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('read_state')
                    ->label('Read/Unread')
                    ->options([
                        'unread' => 'Unread',
                        'read' => 'Read',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'unread' => $query->unread(),
                        'read' => $query->read(),
                        default => $query,
                    }),
                SelectFilter::make('priority')
                    ->options(SystemNotification::priorityOptions()),
                SelectFilter::make('category')
                    ->options(SystemNotification::categoryOptions()),
                SelectFilter::make('notification_type')
                    ->label('Type')
                    ->options(SystemNotification::typeOptions()),
            ])
            ->recordActions([
                Action::make('openRelated')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (SystemNotification $record): ?string => $record->action_url)
                    ->openUrlInNewTab(false)
                    ->visible(fn (SystemNotification $record): bool => filled($record->action_url)),
                Action::make('markAsRead')
                    ->label('Mark as Read')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (SystemNotification $record): bool => $record->isUnread() && (auth()->user()?->can('markRead', $record) ?? false))
                    ->action(fn (SystemNotification $record): SystemNotification => $record->markAsRead()),
                Action::make('markAsUnread')
                    ->label('Mark as Unread')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (SystemNotification $record): bool => $record->isRead() && (auth()->user()?->can('markUnread', $record) ?? false))
                    ->action(fn (SystemNotification $record): SystemNotification => $record->markAsUnread()),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('viewAny', SystemNotification::class) ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', SystemNotification::class) ?? false;
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

    public static function getPages(): array
    {
        return [
            'index' => ListSystemNotifications::route('/'),
        ];
    }
}
