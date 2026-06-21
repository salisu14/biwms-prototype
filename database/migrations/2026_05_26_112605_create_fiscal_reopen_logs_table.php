<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_reopen_logs', function (Blueprint $table) {
            $table->id();
            $table->date('previous_allow_posting_from')->nullable();
            $table->date('previous_allow_posting_to')->nullable();
            $table->date('new_allow_posting_from');
            $table->date('new_allow_posting_to');
            $table->string('reason', 255);
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_reopen_logs');
    }
};
