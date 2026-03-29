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
        Schema::create('bins', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('zone_id')->nullable()->constrained('zones');

            $table->string('description')->nullable();
            $table->enum('bin_type', array_column(\App\Enums\BinType::cases(), 'value'))
                ->default('STORAGE');

            // Capacity
            $table->decimal('maximum_weight', 10, 4)->nullable();
            $table->decimal('maximum_volume', 10, 4)->nullable();

            // Blocking
            $table->boolean('blocked')->default(false);
            $table->boolean('block_movement')->default(false);
            $table->boolean('block_negative')->default(false);

            // Dedicated/Pick
            $table->boolean('dedicated')->default(false); // Item-specific
            $table->string('dedicated_item_id', 20)->nullable(); // If dedicated

            $table->timestamps();

            $table->unique(['code', 'location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bins');
    }
};
