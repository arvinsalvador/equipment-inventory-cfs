<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('browser_push_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('browser_push_subscriptions', 'endpoint_hash')) {
                $table->string('endpoint_hash', 64)->nullable()->after('endpoint');
            }

            if (! Schema::hasColumn('browser_push_subscriptions', 'browser')) {
                $table->string('browser')->nullable()->after('device_name');
            }

            if (! Schema::hasColumn('browser_push_subscriptions', 'platform')) {
                $table->string('platform')->nullable()->after('browser');
            }

            if (! Schema::hasColumn('browser_push_subscriptions', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('last_seen_at');
            }
        });

        DB::table('browser_push_subscriptions')
            ->whereNull('endpoint_hash')
            ->orderBy('id')
            ->lazyById()
            ->each(fn (object $subscription): int => DB::table('browser_push_subscriptions')
                ->where('id', $subscription->id)
                ->update(['endpoint_hash' => hash('sha256', $subscription->endpoint)]));

        DB::table('browser_push_subscriptions')
            ->select('user_id', 'endpoint_hash', DB::raw('MAX(id) as keep_id'), DB::raw('COUNT(*) as duplicate_count'))
            ->whereNotNull('endpoint_hash')
            ->groupBy('user_id', 'endpoint_hash')
            ->having('duplicate_count', '>', 1)
            ->get()
            ->each(fn (object $group): int => DB::table('browser_push_subscriptions')
                ->where('user_id', $group->user_id)
                ->where('endpoint_hash', $group->endpoint_hash)
                ->where('id', '!=', $group->keep_id)
                ->delete());

        Schema::table('browser_push_subscriptions', function (Blueprint $table): void {
            $table->unique(['user_id', 'endpoint_hash'], 'browser_push_user_endpoint_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('browser_push_subscriptions', function (Blueprint $table): void {
            $table->dropUnique('browser_push_user_endpoint_hash_unique');
            $table->dropColumn(['endpoint_hash', 'browser', 'platform', 'last_used_at']);
        });
    }
};
