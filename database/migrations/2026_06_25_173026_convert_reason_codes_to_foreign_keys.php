<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reason_codes', function (Blueprint $table) {
            // 1. Add new ID-based columns (nullable initially)
            $table->foreignId('default_location_id')
                ->nullable()
                ->after('description')
                ->constrained('locations')
                ->nullOnDelete();

            $table->foreignId('default_bin_id')
                ->nullable()
                ->after('default_location_id')
                ->constrained('bins')
                ->nullOnDelete();
        });

        // 2. Migrate existing data from codes to IDs
        $reasonCodes = DB::table('reason_codes')->get();

        foreach ($reasonCodes as $rc) {
            $updates = [];

            // Convert location code → location id
            if (!empty($rc->default_location_code)) {
                $location = DB::table('locations')
                    ->where('code', $rc->default_location_code)
                    ->first();

                if ($location) {
                    $updates['default_location_id'] = $location->id;
                }
            }

            // Convert bin code → bin id
            if (!empty($rc->default_bin_code)) {
                $bin = DB::table('bins')
                    ->where('bin_code', $rc->default_bin_code)
                    ->first();

                if ($bin) {
                    $updates['default_bin_id'] = $bin->id;
                }
            }

            if (!empty($updates)) {
                DB::table('reason_codes')
                    ->where('id', $rc->id)
                    ->update($updates);
            }
        }

        // 3. Remove old code columns
        Schema::table('reason_codes', function (Blueprint $table) {
            $table->dropColumn(['default_location_code', 'default_bin_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reason_codes', function (Blueprint $table) {
            // 1. Re-add code columns
            $table->string('default_location_code', 20)->nullable()->after('description');
            $table->string('default_bin_code', 20)->nullable()->after('default_location_code');
        });

        // 2. Migrate back from IDs to codes
        $reasonCodes = DB::table('reason_codes')->get();

        foreach ($reasonCodes as $rc) {
            $updates = [];

            if (!empty($rc->default_location_id)) {
                $location = DB::table('locations')
                    ->where('id', $rc->default_location_id)
                    ->first();

                if ($location) {
                    $updates['default_location_code'] = $location->code;
                }
            }

            if (!empty($rc->default_bin_id)) {
                $bin = DB::table('bins')
                    ->where('id', $rc->default_bin_id)
                    ->first();

                if ($bin) {
                    $updates['default_bin_code'] = $bin->bin_code;
                }
            }

            if (!empty($updates)) {
                DB::table('reason_codes')
                    ->where('id', $rc->id)
                    ->update($updates);
            }
        }

        // 3. Remove ID columns
        Schema::table('reason_codes', function (Blueprint $table) {
            $table->dropForeign(['default_location_id']);
            $table->dropForeign(['default_bin_id']);
            $table->dropColumn(['default_location_id', 'default_bin_id']);
        });
    }
};
