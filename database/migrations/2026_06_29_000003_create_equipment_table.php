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
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_code')->unique();
            $table->string('property_number')->nullable();
            $table->string('equipment_name');
            $table->foreignId('equipment_category_id')->constrained('equipment_categories')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 12, 2)->nullable();
            $table->foreignId('current_location_id')->constrained('locations')->restrictOnDelete();
            $table->string('custodian')->nullable();
            $table->string('condition');
            $table->string('operational_status');
            $table->string('maintenance_frequency')->nullable();
            $table->date('last_maintenance_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->date('warranty_expiration_date')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
