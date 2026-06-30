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
        if (! Schema::hasColumn('maintenance_recommendations', 'suggested_action_type')) {
            Schema::table('maintenance_recommendations', function (Blueprint $table) {
                $table->string('suggested_action_type')->nullable()->after('metadata');
                $table->string('action_status')->nullable()->default('Pending')->after('suggested_action_type');
                $table->timestamp('actioned_at')->nullable()->after('action_status');
                $table->foreignId('actioned_by')->nullable()->after('actioned_at');
                $table->text('action_notes')->nullable()->after('actioned_by');
                $table->foreignId('linked_work_order_id')->nullable()->after('action_notes');
                $table->foreignId('linked_maintenance_schedule_id')->nullable()->after('linked_work_order_id');
            });
        }

        Schema::table('maintenance_recommendations', function (Blueprint $table) {
            $table->foreign('actioned_by', 'mr_actioned_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('linked_work_order_id', 'mr_linked_wo_fk')->references('id')->on('work_orders')->nullOnDelete();
            $table->foreign('linked_maintenance_schedule_id', 'mr_linked_schedule_fk')->references('id')->on('maintenance_schedules')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_recommendations', function (Blueprint $table) {
            $table->dropForeign('mr_linked_schedule_fk');
            $table->dropForeign('mr_linked_wo_fk');
            $table->dropForeign('mr_actioned_by_fk');
            $table->dropColumn([
                'linked_maintenance_schedule_id',
                'linked_work_order_id',
                'action_notes',
                'actioned_by',
                'actioned_at',
                'action_status',
                'suggested_action_type',
            ]);
        });
    }
};
