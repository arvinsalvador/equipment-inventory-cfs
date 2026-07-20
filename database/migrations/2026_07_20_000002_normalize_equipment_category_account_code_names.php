<?php

use App\Support\OfficialEquipmentInventoryDocumentParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (OfficialEquipmentInventoryDocumentParser::APPROVED_CATEGORIES as $accountCode => $name) {
            $prefixedName = $accountCode.' - '.$name;
            $canonical = DB::table('equipment_categories')
                ->where('account_code', $accountCode)
                ->orWhere('name', $name)
                ->orWhere('name', $prefixedName)
                ->orderByRaw('account_code = ? desc', [$accountCode])
                ->orderByRaw('name = ? desc', [$name])
                ->first();

            if (! $canonical) {
                continue;
            }

            DB::table('equipment_categories')
                ->where('id', $canonical->id)
                ->update([
                    'account_code' => $accountCode,
                    'name' => $name,
                    'updated_at' => now(),
                ]);

            DB::table('equipment_categories')
                ->where('id', '!=', $canonical->id)
                ->where(function ($query) use ($accountCode, $name, $prefixedName): void {
                    $query->where('account_code', $accountCode)
                        ->orWhere('name', $name)
                        ->orWhere('name', $prefixedName);
                })
                ->delete();
        }
    }

    public function down(): void
    {
        // Category normalization is intentionally not reversed.
    }
};
