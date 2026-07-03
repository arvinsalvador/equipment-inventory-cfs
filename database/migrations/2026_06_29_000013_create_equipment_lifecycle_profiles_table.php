<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_lifecycle_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->unique()->constrained('equipment')->cascadeOnDelete();
            $table->unsignedInteger('expected_useful_life_years')->nullable();
            $table->date('estimated_end_of_life_date')->nullable();
            $table->integer('estimated_remaining_life_months')->nullable();
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->string('health_grade')->nullable();
            $table->string('lifecycle_status')->nullable();
            $table->string('replacement_recommendation')->nullable();
            $table->text('replacement_reason')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_lifecycle_profiles');
    }
};
