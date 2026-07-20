<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_categories', function (Blueprint $table): void {
            $table->string('account_code', 20)->nullable()->after('id')->unique();
        });

        Schema::table('budget_plans', function (Blueprint $table): void {
            $table->date('budget_date')->nullable()->after('fiscal_year');
            $table->string('funds')->nullable()->after('budget_date');
            $table->string('purchase_order_number')->nullable()->after('funds');
        });
    }

    public function down(): void
    {
        Schema::table('budget_plans', function (Blueprint $table): void {
            $table->dropColumn(['budget_date', 'funds', 'purchase_order_number']);
        });

        Schema::table('equipment_categories', function (Blueprint $table): void {
            $table->dropUnique(['account_code']);
            $table->dropColumn('account_code');
        });
    }
};
