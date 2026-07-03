<?php

use App\Services\EquipmentLifecycleAnalyzer;
use App\Services\MaintenanceRecommendationEngine;
use App\Services\SystemNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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

Artisan::command('notifications:generate', function (SystemNotificationService $notifications): int {
    $summary = $notifications->generateAll();

    $this->info('System notifications generated.');
    $this->line('Notifications created: '.$summary['created']);
    $this->line('Duplicates skipped: '.$summary['duplicates_skipped']);
    $this->line('Categories checked: '.implode(', ', $summary['categories_checked']));

    return self::SUCCESS;
})->purpose('Generate in-app system notifications for maintenance operations');
