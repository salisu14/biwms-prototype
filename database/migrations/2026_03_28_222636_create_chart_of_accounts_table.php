<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 20)->unique(); // 40100, 50100, etc.
            $table->string('name');

            $table->enum('account_type', array_column(AccountType::cases(), 'value'))
                ->default('REVENUE');

            $table->enum('account_category', array_column(AccountCategory::cases(), 'value'))
                ->default('RECEIVABLE');

            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('direct_posting')->default(false); // Disable for control accounts
            $table->boolean('blocked')->default(false);
            $table->foreignId('parent_account_id')->nullable()->constrained('chart_of_accounts');
            $table->timestamps();

            $table->index('account_type');
            $table->index('account_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
