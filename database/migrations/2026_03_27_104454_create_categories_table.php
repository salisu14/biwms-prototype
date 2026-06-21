<?php

use App\Enums\CategoryType;
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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_code', 50)->unique();
            $table->string('category_name', 100);
            $table->string('hierarchy_path', 255); // e.g., "ROOT.THERAPEUTIC.IMMUNE"
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories', 'id')
                ->onDelete('cascade');
            $table->integer('level')->default(0); // 0=root, 1=category, 2=subcategory, etc.
            $table->integer('sort_order')->default(0);
            // FIX: Dynamically set enum values from the PHP Enum to ensure consistency
            $table->enum('category_type', array_column(CategoryType::cases(), 'value'))
                ->default('THERAPEUTIC');
            $table->text('description')->nullable();
            $table->json('attributes')->nullable(); // Flexible attributes per category
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['category_type', 'level', 'is_active']);
            $table->index('hierarchy_path');
            $table->index(['parent_id', 'is_active']);
            $table->index(['sort_order', 'is_active']); // Optional index
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
