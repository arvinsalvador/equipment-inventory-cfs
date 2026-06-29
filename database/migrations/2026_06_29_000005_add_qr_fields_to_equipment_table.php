<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('qr_identifier')->nullable()->unique()->after('equipment_code');
            $table->string('qr_code_path')->nullable()->after('photo_path');
            $table->timestamp('qr_code_generated_at')->nullable()->after('qr_code_path');
        });

        DB::table('equipment')
            ->whereNull('qr_identifier')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($equipment): void {
                foreach ($equipment as $item) {
                    DB::table('equipment')
                        ->where('id', $item->id)
                        ->update(['qr_identifier' => (string) Str::uuid()]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropUnique('equipment_qr_identifier_unique');
            $table->dropColumn([
                'qr_identifier',
                'qr_code_path',
                'qr_code_generated_at',
            ]);
        });
    }
};
