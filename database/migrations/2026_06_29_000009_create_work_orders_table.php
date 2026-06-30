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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('work_order_number')->unique();
            $table->foreignId('maintenance_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('problem_description');
            $table->string('priority');
            $table->string('status')->default('Submitted');
            $table->text('findings')->nullable();
            $table->text('action_performed')->nullable();
            $table->text('completion_remarks')->nullable();
            $table->string('final_equipment_condition')->nullable();
            $table->string('final_operational_status')->nullable();
            $table->text('beyond_repair_reason')->nullable();
            $table->text('recommended_action')->nullable();
            $table->text('on_hold_reason')->nullable();
            $table->text('required_parts')->nullable();
            $table->text('rejection_or_cancellation_reason')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->date('due_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
