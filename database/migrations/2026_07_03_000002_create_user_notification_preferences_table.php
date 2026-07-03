<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('maintenance_reminders')->default(true);
            $table->boolean('work_order_alerts')->default(true);
            $table->boolean('maintenance_request_alerts')->default(true);
            $table->boolean('ai_recommendation_alerts')->default(true);
            $table->boolean('lifecycle_alerts')->default(true);
            $table->boolean('warranty_alerts')->default(true);
            $table->boolean('evidence_alerts')->default(true);
            $table->boolean('system_alerts')->default(true);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
