<?php

use App\Enums\ApprovalStatus;
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
        Schema::create('sales_credit_memos', function (Blueprint $table) {
            $table->id();
            $table->string('memo_number')->unique();
            $table->decimal('total_amount', 15, 2);

            $table->enum('status', array_column(ApprovalStatus::cases(), 'value'))
                ->default('draft');

            $table->text('reason')->nullable();
            $table->date('effective_date');

            $table->string('currency_code')->nullable();

            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('posted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('sales_invoice_id')->constrained('users')->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_credit_memos');
    }
};
