<?php

use App\Enums\SourceCode;
use App\Enums\TemplateType;
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
        Schema::create('item_journal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->string('description');
            $table->enum('template_type', array_column(TemplateType::cases(), 'value'));

            $table->enum('source_code', array_column(SourceCode::cases(), 'value'));

            $table->string('reason_code', 20)->nullable();
            $table->boolean('recurring')->default(false);
            $table->boolean('blocked')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_journal_templates');
    }
};
