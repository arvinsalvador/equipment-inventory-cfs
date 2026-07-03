<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->decimal('labor_cost', 12, 2)->nullable()->after('due_date');
            $table->decimal('parts_cost', 12, 2)->nullable()->after('labor_cost');
            $table->decimal('external_service_cost', 12, 2)->nullable()->after('parts_cost');
            $table->decimal('total_cost', 12, 2)->nullable()->after('external_service_cost');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn([
                'labor_cost',
                'parts_cost',
                'external_service_cost',
                'total_cost',
            ]);
        });
    }
};
