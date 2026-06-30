<?php

namespace App\Filament\Resources\MaintenanceRecommendations;

use App\Filament\Resources\MaintenanceRecommendations\Pages\ListMaintenanceRecommendations;
use App\Filament\Resources\MaintenanceRecommendations\Pages\ViewMaintenanceRecommendation;
use App\Models\Equipment;
use App\Models\MaintenanceRecommendation;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRecommendationResource extends Resource
{
    protected static ?string $model = MaintenanceRecommendation::class;

    protected static ?string $navigationLabel = 'Recommendations';

    protected static ?string $modelLabel = 'Recommendation';

    protected static ?string $pluralModelLabel = 'Recommendations';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance Management';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Equipment details')
                    ->schema([
                        TextEntry::make('equipment.equipment_code')->label('Equipment code'),
                        TextEntry::make('equipment.equipment_name')->label('Equipment name'),
                        TextEntry::make('equipment.condition')->label('Condition')->badge(),
                        TextEntry::make('equipment.operational_status')->label('Operational status')->badge(),
                    ]),
                Section::make('Recommendation')
                    ->schema([
                        TextEntry::make('rule_key')->label('Rule key')->badge(),
                        TextEntry::make('title'),
                        TextEntry::make('explanation')->columnSpanFull(),
                        TextEntry::make('risk_level')->label('Risk level')->badge()->color(fn (string $state): string => self::riskColor($state)),
                        TextEntry::make('status')->badge()->color(fn (string $state): string => self::statusColor($state)),
                        TextEntry::make('recommended_action')->label('Recommended action')->columnSpanFull(),
                        TextEntry::make('generated_at')->label('Generated date')->dateTime(),
                    ]),
                Section::make('Review and resolution')
                    ->schema([
                        TextEntry::make('reviewedBy.name')->label('Reviewed by')->placeholder('Not reviewed'),
                        TextEntry::make('reviewed_at')->label('Reviewed date')->dateTime()->placeholder('Not reviewed'),
                        TextEntry::make('resolvedBy.name')->label('Resolved by')->placeholder('Not resolved'),
                        TextEntry::make('resolved_at')->label('Resolved date')->dateTime()->placeholder('Not resolved'),
                        TextEntry::make('metadata')
                            ->label('Metadata')
                            ->state(fn (MaintenanceRecommendation $record): string => $record->metadata === null || $record->metadata === []
                                ? 'None'
                                : json_encode($record->metadata, JSON_PRETTY_PRINT))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['equipment', 'reviewedBy', 'resolvedBy'])->latest('generated_at'))
            ->columns([
                TextColumn::make('equipment.equipment_code')
                    ->label('Equipment')
                    ->formatStateUsing(fn (MaintenanceRecommendation $record): string => $record->equipment->equipment_code.' - '.$record->equipment->equipment_name)
                    ->searchable(['equipment.equipment_code', 'equipment.equipment_name'])
                    ->sortable(),
                TextColumn::make('rule_key')
                    ->label('Rule')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('explanation')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('recommended_action')
                    ->label('Recommended action')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('risk_level')
                    ->label('Risk level')
                    ->badge()
                    ->color(fn (string $state): string => self::riskColor($state))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->sortable(),
                TextColumn::make('generated_at')
                    ->label('Generated date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->label('Reviewed date')
                    ->dateTime()
                    ->placeholder('Not reviewed')
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->label('Resolved date')
                    ->dateTime()
                    ->placeholder('Not resolved')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('risk_level')
                    ->label('Risk level')
                    ->options(MaintenanceRecommendation::riskLevelOptions()),
                SelectFilter::make('status')
                    ->options(MaintenanceRecommendation::statusOptions()),
                SelectFilter::make('rule_key')
                    ->label('Rule')
                    ->options(fn (): array => MaintenanceRecommendation::query()
                        ->orderBy('rule_key')
                        ->pluck('rule_key', 'rule_key')
                        ->all()),
                SelectFilter::make('equipment_id')
                    ->label('Equipment')
                    ->options(fn (): array => Equipment::query()
                        ->orderBy('equipment_code')
                        ->get()
                        ->mapWithKeys(fn (Equipment $equipment): array => [
                            $equipment->id => $equipment->equipment_code.' - '.$equipment->equipment_name,
                        ])
                        ->all())
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                self::markReviewedAction(),
                self::markResolvedAction(),
                self::dismissAction(),
            ]);
    }

    public static function markReviewedAction(): Action
    {
        return Action::make('markReviewed')
            ->label('Mark Reviewed')
            ->icon('heroicon-o-eye')
            ->requiresConfirmation()
            ->visible(fn (MaintenanceRecommendation $record): bool => ! $record->isReviewed()
                && ! $record->isResolved()
                && ! $record->isDismissed()
                && (auth()->user()?->can('review', $record) ?? false))
            ->action(function (MaintenanceRecommendation $record): void {
                $record->markReviewed(auth()->user());

                Notification::make()
                    ->title('Recommendation marked reviewed')
                    ->success()
                    ->send();
            });
    }

    public static function markResolvedAction(): Action
    {
        return Action::make('markResolved')
            ->label('Mark Resolved')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->visible(fn (MaintenanceRecommendation $record): bool => ! $record->isResolved()
                && ! $record->isDismissed()
                && (auth()->user()?->can('resolve', $record) ?? false))
            ->action(function (MaintenanceRecommendation $record): void {
                $record->markResolved(auth()->user());

                Notification::make()
                    ->title('Recommendation marked resolved')
                    ->success()
                    ->send();
            });
    }

    public static function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label('Dismiss')
            ->icon('heroicon-o-x-circle')
            ->requiresConfirmation()
            ->visible(fn (MaintenanceRecommendation $record): bool => ! $record->isResolved()
                && ! $record->isDismissed()
                && (auth()->user()?->can('dismiss', $record) ?? false))
            ->action(function (MaintenanceRecommendation $record): void {
                $record->dismiss(auth()->user());

                Notification::make()
                    ->title('Recommendation dismissed')
                    ->success()
                    ->send();
            });
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
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
            'index' => ListMaintenanceRecommendations::route('/'),
            'view' => ViewMaintenanceRecommendation::route('/{record}'),
        ];
    }

    private static function riskColor(string $state): string
    {
        return match ($state) {
            'Critical' => 'danger',
            'High' => 'warning',
            'Low' => 'gray',
            default => 'info',
        };
    }

    private static function statusColor(string $state): string
    {
        return match ($state) {
            'Resolved' => 'success',
            'Dismissed' => 'gray',
            'Reviewed' => 'info',
            default => 'warning',
        };
    }
}
