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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_required')->default(false)->after('two_factor_confirmed_at');
            $table->foreignId('two_factor_enabled_by')->nullable()->after('two_factor_required')->constrained('users')->nullOnDelete();
            $table->timestamp('two_factor_disabled_at')->nullable()->after('two_factor_enabled_by');
            $table->foreignId('two_factor_disabled_by')->nullable()->after('two_factor_disabled_at')->constrained('users')->nullOnDelete();
            $table->timestamp('two_factor_last_challenged_at')->nullable()->after('two_factor_disabled_by');
            $table->timestamp('two_factor_reset_at')->nullable()->after('two_factor_last_challenged_at');
            $table->foreignId('two_factor_reset_by')->nullable()->after('two_factor_reset_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('two_factor_reset_by');
            $table->dropColumn('two_factor_reset_at');
            $table->dropColumn('two_factor_last_challenged_at');
            $table->dropConstrainedForeignId('two_factor_disabled_by');
            $table->dropColumn('two_factor_disabled_at');
            $table->dropConstrainedForeignId('two_factor_enabled_by');
            $table->dropColumn('two_factor_required');
        });
    }
};
