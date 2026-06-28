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
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable()->after('source_id')->constrained('users')->nullOnDelete();
            $table->string('subject_type')->nullable()->after('actor_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->foreignId('business_id')->nullable()->after('metadata')->constrained()->nullOnDelete();
            $table->foreignId('factory_id')->nullable()->after('business_id')->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('factory_id')->constrained('locations')->nullOnDelete();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex(['actor_id', 'occurred_at']);
            $table->dropConstrainedForeignId('actor_id');
            $table->dropConstrainedForeignId('business_id');
            $table->dropConstrainedForeignId('factory_id');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn(['subject_type', 'subject_id']);
        });
    }
};
