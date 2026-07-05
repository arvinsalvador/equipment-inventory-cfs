<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('maintenance_recommendations', 'linked_maintenance_request_id')) {
            Schema::table('maintenance_recommendations', function (Blueprint $table): void {
                $table->foreignId('linked_maintenance_request_id')
                    ->nullable()
                    ->after('linked_maintenance_schedule_id');
            });
        }

        Schema::table('maintenance_recommendations', function (Blueprint $table): void {
            $table->foreign('linked_maintenance_request_id', 'mr_linked_request_fk')
                ->references('id')
                ->on('maintenance_requests')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('maintenance_recommendations', 'linked_maintenance_request_id')) {
            Schema::table('maintenance_recommendations', function (Blueprint $table): void {
                $table->dropForeign('mr_linked_request_fk');
                $table->dropColumn('linked_maintenance_request_id');
            });
        }
    }
};
