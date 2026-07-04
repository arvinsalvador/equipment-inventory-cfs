<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('plan_number')->unique();
            $table->string('title');
            $table->unsignedSmallInteger('fiscal_year');
            $table->text('description')->nullable();
            $table->decimal('total_estimated_budget', 14, 2)->nullable();
            $table->string('status');
            $table->foreignId('prepared_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['fiscal_year', 'status']);
            $table->index('prepared_by');
            $table->index('approved_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plans');
    }
};
