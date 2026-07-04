<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('value_type')->default('string');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_editable')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['group', 'key']);
            $table->index('group');
            $table->index('key');
            $table->index('is_public');
            $table->index('is_editable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
