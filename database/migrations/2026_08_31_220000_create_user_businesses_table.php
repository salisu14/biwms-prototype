<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_businesses')) {
            Schema::create('user_businesses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'business_id']);
                $table->index(['business_id', 'user_id']);
            });
        }

        // Preserve the existing single-business installation contract without
        // guessing access when multiple active businesses already exist.
        $activeBusinesses = DB::table('businesses')->where('is_active', true)->pluck('id');
        if ($activeBusinesses->count() !== 1) {
            return;
        }

        $businessId = (int) $activeBusinesses->first();
        $now = now();
        $rows = DB::table('users')->pluck('id')->map(fn ($userId): array => [
            'user_id' => $userId,
            'business_id' => $businessId,
            'granted_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            DB::table('user_businesses')->upsert($rows, ['user_id', 'business_id'], ['updated_at']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_businesses');
    }
};
