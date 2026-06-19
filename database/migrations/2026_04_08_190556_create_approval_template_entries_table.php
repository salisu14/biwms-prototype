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
        Schema::create('approval_template_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_template_id')->constrained()->onDelete('cascade');
            $table->integer('sequence_no');
            $table->string('approver_type', 20); // 'user', 'role', 'hierarchy', 'dimension'
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->string('approver_role', 50)->nullable();
            $table->integer('hierarchy_levels')->nullable(); // For hierarchy type (1 = direct manager)
            $table->string('dimension_code', 20)->nullable();
            $table->boolean('allow_delegation')->default(false);
            $table->timestamps();

            $table->unique(['approval_template_id', 'sequence_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_template_entries');
    }
};
