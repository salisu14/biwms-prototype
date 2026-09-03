<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gl_entries')) {
            return;
        }

        Schema::table('gl_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('gl_entries', 'payment_application_id')) {
                $table->foreignId('payment_application_id')
                    ->nullable()
                    ->constrained('payment_applications')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('gl_entries', 'reversal_of_gl_entry_id')) {
                $table->foreignId('reversal_of_gl_entry_id')
                    ->nullable()
                    ->constrained('gl_entries')
                    ->nullOnDelete();
            }
        });

        $indexes = collect(Schema::getIndexes('gl_entries'))->pluck('name');
        if (! $indexes->contains('gl_entries_payment_application_index')) {
            Schema::table('gl_entries', function (Blueprint $table): void {
                $table->index(['payment_application_id'], 'gl_entries_payment_application_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('gl_entries')) {
            return;
        }

        Schema::table('gl_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('gl_entries', 'payment_application_id')) {
                $table->dropConstrainedForeignId('payment_application_id');
            }

            if (Schema::hasColumn('gl_entries', 'reversal_of_gl_entry_id')) {
                $table->dropConstrainedForeignId('reversal_of_gl_entry_id');
            }
        });
    }
};
