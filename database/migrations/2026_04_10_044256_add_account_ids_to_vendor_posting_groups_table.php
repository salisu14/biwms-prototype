<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor_posting_groups', function (Blueprint $table) {
            $table->foreignId('payables_account_id')
                ->nullable()
                ->after('payables_account')
                ->constrained('chart_of_accounts');
            
            $table->foreignId('payment_disc_debit_account_id')
                ->nullable()
                ->after('payment_disc_debit_acc')
                ->constrained('chart_of_accounts');
            
            $table->foreignId('payment_disc_credit_account_id')
                ->nullable()
                ->after('payment_disc_credit_acc')
                ->constrained('chart_of_accounts');

            $table->foreignId('invoice_rounding_account_id')
                ->nullable()
                ->after('invoice_rounding_account')
                ->constrained('chart_of_accounts');
        });

        // Migrate existing data
        $this->migrateExistingData();
    }

    private function migrateExistingData()
    {
        $vpgs = DB::table('vendor_posting_groups')->get();
        foreach ($vpgs as $vpg) {
            $updates = [];

            if ($vpg->payables_account) {
                $account = DB::table('chart_of_accounts')->where('account_number', $vpg->payables_account)->first();
                if ($account) {
                    $updates['payables_account_id'] = $account->id;
                }
            }

            if ($vpg->payment_disc_debit_acc) {
                $account = DB::table('chart_of_accounts')->where('account_number', $vpg->payment_disc_debit_acc)->first();
                if ($account) {
                    $updates['payment_disc_debit_account_id'] = $account->id;
                }
            }

            if ($vpg->payment_disc_credit_acc) {
                $account = DB::table('chart_of_accounts')->where('account_number', $vpg->payment_disc_credit_acc)->first();
                if ($account) {
                    $updates['payment_disc_credit_account_id'] = $account->id;
                }
            }

            if ($vpg->invoice_rounding_account) {
                $account = DB::table('chart_of_accounts')->where('account_number', $vpg->invoice_rounding_account)->first();
                if ($account) {
                    $updates['invoice_rounding_account_id'] = $account->id;
                }
            }

            if (!empty($updates)) {
                DB::table('vendor_posting_groups')->where('id', $vpg->id)->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_posting_groups', function (Blueprint $table) {
            $table->dropForeign(['payables_account_id']);
            $table->dropForeign(['payment_disc_debit_account_id']);
            $table->dropForeign(['payment_disc_credit_account_id']);
            $table->dropForeign(['invoice_rounding_account_id']);
            
            $table->dropColumn([
                'payables_account_id',
                'payment_disc_debit_account_id',
                'payment_disc_credit_account_id',
                'invoice_rounding_account_id'
            ]);
        });
    }
};
