<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class OfflineQueue extends Page
{
    protected string $view = 'filament.pages.offline-queue';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance Management';

    protected static ?string $navigationLabel = 'Offline Queue';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access admin panel') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Offline Queue';
    }
}
