<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => auth()->user()?->can('audit.export') ?? false)
                ->authorize(fn (): bool => auth()->user()?->can('audit.export') ?? false)
                ->action(function (AuditLogService $auditLogService): StreamedResponse {
                    $auditLogService->logExport('Audit Trail', 'Audit trail CSV exported.', auth()->user());

                    return response()->streamDownload(function (): void {
                        $handle = fopen('php://output', 'w');

                        fputcsv($handle, [
                            'Date',
                            'User',
                            'Action',
                            'Module',
                            'Entity Type',
                            'Entity ID',
                            'Description',
                            'IP Address',
                            'URL',
                        ]);

                        AuditLog::query()
                            ->with('user')
                            ->latest('created_at')
                            ->each(function (AuditLog $auditLog) use ($handle): void {
                                fputcsv($handle, [
                                    $auditLog->created_at?->toDateTimeString(),
                                    $auditLog->user?->name ?? 'System',
                                    $auditLog->action,
                                    $auditLog->module,
                                    $auditLog->entity_type,
                                    $auditLog->entity_id,
                                    $auditLog->description,
                                    $auditLog->ip_address,
                                    $auditLog->url,
                                ]);
                            });

                        fclose($handle);
                    }, 'audit-trail-'.now()->format('Ymd-His').'.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
        ];
    }
}
