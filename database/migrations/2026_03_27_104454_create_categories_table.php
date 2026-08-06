<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();

            $table->string('category_code', 50)->unique();
            $table->string('category_name', 100);
            $table->string('hierarchy_path', 255);

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->unsignedInteger('level')->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            /*
             * Keep historical migration values explicit.
             * Do not derive these from the live PHP enum.
             */
            $table->enum('category_type', [
                'FINISHED_GOOD',
                'SEMI_FINISHED',
                'RAW_MATERIAL',
                'PACKAGING',
                'CONSUMABLE',
                'SPARE_PART',
            ]);

            $table->text('description')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(
                ['category_type', 'level', 'is_active'],
                'categories_type_level_active_index',
            );

            $table->index('hierarchy_path');
            $table->index(['parent_id', 'is_active']);
            $table->index(['sort_order', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
