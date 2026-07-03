<?php

use App\Services\EquipmentLifecycleAnalyzer;
use App\Services\MaintenanceRecommendationEngine;
use App\Services\NotificationEmailService;
use App\Services\SystemNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('maintenance:generate-recommendations', function (MaintenanceRecommendationEngine $engine): int {
    $summary = $engine->generateForAllEquipment();

    $this->info('Maintenance recommendations generated.');
    $this->line('Equipment checked: '.$summary['equipment_checked']);
    $this->line('Recommendations created: '.$summary['created']);
    $this->line('Recommendations updated: '.$summary['updated']);

    return self::SUCCESS;
})->purpose('Generate rule-based maintenance recommendations for all equipment');

Artisan::command('equipment:analyze-lifecycle', function (EquipmentLifecycleAnalyzer $analyzer): int {
    $summary = $analyzer->analyzeAll();

    $this->info('Equipment lifecycle analysis completed.');
    $this->line('Equipment analyzed: '.$summary['equipment_analyzed']);
    $this->line('Replacement candidates: '.$summary['replacement_candidates']);
    $this->line('Critical equipment: '.$summary['critical_equipment']);
    $this->line('High-maintenance equipment: '.$summary['high_maintenance_equipment']);

    return self::SUCCESS;
})->purpose('Analyze equipment lifecycle and cost decision-support profiles');

Artisan::command('notifications:generate {--send-critical-emails}', function (
    SystemNotificationService $notifications,
    NotificationEmailService $email
): int {
    $summary = $notifications->generateAll();

    $this->info('System notifications generated.');
    $this->line('Notifications created: '.$summary['created']);
    $this->line('Duplicates skipped: '.$summary['duplicates_skipped']);
    $this->line('Categories checked: '.implode(', ', $summary['categories_checked']));

    if ($this->option('send-critical-emails')) {
        $emailSummary = $email->sendImmediateCriticalEmails();

        $this->info('Immediate critical emails processed.');
        $this->line('Users checked: '.$emailSummary['users_checked']);
        $this->line('Emails sent: '.$emailSummary['emails_sent']);
        $this->line('Skipped: '.$emailSummary['skipped']);
        $this->line('Failures: '.$emailSummary['failures']);
    }

    return self::SUCCESS;
})->purpose('Generate in-app system notifications for maintenance operations');

Artisan::command('notifications:send-daily-digest', function (NotificationEmailService $email): int {
    $summary = $email->sendDailyDigests();

    $this->info('Daily notification digests processed.');
    $this->line('Users checked: '.$summary['users_checked']);
    $this->line('Emails sent: '.$summary['emails_sent']);
    $this->line('Skipped: '.$summary['skipped']);
    $this->line('Failures: '.$summary['failures']);

    return self::SUCCESS;
})->purpose('Send opted-in daily notification digest emails');

Artisan::command('notifications:send-weekly-digest', function (NotificationEmailService $email): int {
    $summary = $email->sendWeeklyDigests();

    $this->info('Weekly notification digests processed.');
    $this->line('Users checked: '.$summary['users_checked']);
    $this->line('Emails sent: '.$summary['emails_sent']);
    $this->line('Skipped: '.$summary['skipped']);
    $this->line('Failures: '.$summary['failures']);

    return self::SUCCESS;
})->purpose('Send opted-in weekly notification digest emails');

Schedule::command('notifications:generate')->hourly();
Schedule::command('notifications:send-daily-digest')->dailyAt('08:00');
Schedule::command('notifications:send-weekly-digest')->weeklyOn(1, '08:00');
