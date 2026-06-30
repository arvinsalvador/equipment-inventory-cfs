<?php

use App\Services\MaintenanceRecommendationEngine;
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
