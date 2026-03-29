<?php

use App\Enums\ZoneType;
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
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->foreignId('location_id')->constrained('locations');
            $table->string('description');
            $table->enum('zone_type', array_column(ZoneType::cases(), 'value'))
                ->default('RECEIVING');

            $table->string('bin_type_code', 20)->nullable();
            $table->boolean('blocked')->default(false);
            $table->timestamps();

            $table->unique(['code', 'location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
