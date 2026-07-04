<?php

namespace Database\Seeders;

use App\Services\SystemSettingsService;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(SystemSettingsService $settings): void
    {
        $settings->seedDefaultSettings();
    }
}
