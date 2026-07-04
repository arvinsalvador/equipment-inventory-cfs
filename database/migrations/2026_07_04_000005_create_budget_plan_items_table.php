<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->foreignId('asset_action_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_type');
            $table->string('description');
            $table->string('priority');
            $table->decimal('estimated_cost', 12, 2);
            $table->text('justification')->nullable();
            $table->text('forecast_reason')->nullable();
            $table->string('target_period')->nullable();
            $table->string('status');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['budget_plan_id', 'item_type']);
            $table->index(['equipment_id', 'item_type']);
            $table->index('asset_action_request_id');
            $table->index(['priority', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plan_items');
    }
};
