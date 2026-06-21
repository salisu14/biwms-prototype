<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_information', function (Blueprint $table): void {
            $table->foreignId('business_id')
                ->nullable()
                ->after('id')
                ->constrained('businesses')
                ->nullOnDelete();
        });

        // Backfill existing singleton row to the first active business where available.
        $activeBusinessId = DB::table('businesses')
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        if ($activeBusinessId) {
            DB::table('company_information')
                ->whereNull('business_id')
                ->update(['business_id' => $activeBusinessId]);
        }

        Schema::table('company_information', function (Blueprint $table): void {
            $table->unique('business_id', 'company_information_business_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('company_information', function (Blueprint $table): void {
            $table->dropUnique('company_information_business_id_unique');
            $table->dropConstrainedForeignId('business_id');
        });
    }
};
