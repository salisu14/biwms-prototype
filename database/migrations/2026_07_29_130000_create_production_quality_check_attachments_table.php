<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_quality_check_attachments')) {
            return;
        }

        Schema::create('production_quality_check_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_quality_check_id')
                ->constrained('production_quality_checks')
                ->cascadeOnDelete();
            $table->string('disk', 50)->default('local');
            $table->string('path');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index('production_quality_check_id', 'pqca_check_index');
            $table->index(['disk', 'path'], 'pqca_disk_path_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_quality_check_attachments');
    }
};
