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
        Schema::create('sales_quote_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revision_number', 20)->comment('Unique revision number');
            $table->foreignId('sales_quote_id')->constrained()->cascadeOnDelete();
            $table->text('changes'); // JSON of what changed
            $table->text('description')->nullable()->comment('Revision description or notes');
            $table->timestamp('revision_date')->nullable()->comment('Date of this revision');
            $table->softDeletes()->after('revision_date');
            $table->integer('version')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_quote_revisions');
    }
};
