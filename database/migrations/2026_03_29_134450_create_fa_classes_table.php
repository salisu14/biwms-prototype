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
        // FA Classes and Subclasses (for reporting and grouping)
        Schema::create('fa_classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->enum('fa_type', \App\Enums\FixedAssetType::cases());
            $table->foreignId('default_posting_group_id')->nullable()->constrained('fa_posting_groups');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fa_subclasses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fa_class_id')->constrained('fa_classes')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->foreignId('default_posting_group_id')->nullable()->constrained('fa_posting_groups');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['fa_class_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fa_subclasses');
        Schema::dropIfExists('fa_classes');
    }
};
