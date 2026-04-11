<?php

namespace Database\Seeders;

use App\Enums\BankAccountType;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure required GL accounts exist
        $this->createRequiredGlAccounts();

        // Get GL account IDs
        $checkingGl = ChartOfAccount::where('account_number', '10100')->first()?->id;
        $savingsGl = ChartOfAccount::where('account_number', '10200')->first()?->id;
        $moneyMarketGl = ChartOfAccount::where('account_number', '10300')->first()?->id;
        $cdGl = ChartOfAccount::where('account_number', '10400')->first()?->id;
        $foreignUsdGl = ChartOfAccount::where('account_number', '10500')->first()?->id;
        $foreignEurGl = ChartOfAccount::where('account_number', '10510')->first()?->id;
        $foreignGbpGl = ChartOfAccount::where('account_number', '10520')->first()?->id;

        $usd = Currency::where('code', 'USD')->first()?->id;
        $eur = Currency::where('code', 'EUR')->first()?->id;
        $gbp = Currency::where('code', 'GBP')->first()?->id;
        $cad = Currency::where('code', 'CAD')->first()?->id; // CAD might be missing from my previous seeder, I'll add it to CurrencySeeder if needed.
        $ngn = Currency::where('code', 'NGN')->first()?->id;

        $bankAccounts = [
            // Primary Operating Account - USD Checking
            [
                'account_code' => 'BANK-001',
                'account_name' => 'Primary Operating Account',
                'bank_name' => 'JPMorgan Chase Bank',
                'bank_branch' => 'Main Street Branch',
                'account_number' => '000123456789',
                'routing_number' => '021000021',
                'swift_code' => 'CHASUS33',
                'iban' => null,
                'gl_account_id' => $checkingGl,
                'currency_id' => $usd,
                'account_type' => BankAccountType::CHECKING,
                'current_balance' => 125000.00,
                'available_balance' => 115000.00,
                'last_reconciliation_date' => '2026-03-31',
                'last_reconciliation_balance' => 118500.00,
                'next_check_number' => '1001',
                'check_form_id' => 'BUSINESS-01',
                'active' => true,
                'allow_payments' => true,
                'allow_receipts' => true,
            ],
            // Payroll Account - USD Checking
            [
                'account_code' => 'BANK-002',
                'account_name' => 'Payroll Account',
                'bank_name' => 'Bank of America',
                'bank_branch' => 'Corporate Center',
                'account_number' => '000987654321',
                'routing_number' => '026009593',
                'swift_code' => 'BOFAUS3N',
                'iban' => null,
                'gl_account_id' => $checkingGl,
                'currency_id' => $usd,
                'account_type' => BankAccountType::CHECKING,
                'current_balance' => 75000.00,
                'available_balance' => 75000.00,
                'last_reconciliation_date' => '2026-03-31',
                'last_reconciliation_balance' => 72000.00,
                'next_check_number' => '5001',
                'check_form_id' => 'PAYROLL-01',
                'active' => true,
                'allow_payments' => true,
                'allow_receipts' => false,
            ],
            // Tax Reserve Account - USD Checking
            [
                'account_code' => 'BANK-003',
                'account_name' => 'Tax Reserve Account',
                'bank_name' => 'Wells Fargo Bank',
                'bank_branch' => 'Downtown Branch',
                'account_number' => '000456789123',
                'routing_number' => '121000248',
                'swift_code' => 'WFBIUS6S',
                'iban' => null,
                'gl_account_id' => $checkingGl,
                'currency_id' => $usd,
                'account_type' => BankAccountType::CHECKING,
                'current_balance' => 45000.00,
                'available_balance' => 45000.00,
                'last_reconciliation_date' => '2026-03-15',
                'last_reconciliation_balance' => 42000.00,
                'next_check_number' => '2001',
                'check_form_id' => 'TAX-01',
                'active' => true,
                'allow_payments' => true,
                'allow_receipts' => false,
            ],
            // General Savings - USD Savings
            [
                'account_code' => 'BANK-004',
                'account_name' => 'General Savings',
                'bank_name' => 'Capital One Bank',
                'bank_branch' => 'Savings Division',
                'account_number' => '001234567890',
                'routing_number' => '056073502',
                'swift_code' => 'NFBKUSF1',
                'iban' => null,
                'gl_account_id' => $savingsGl,
                'currency_id' => $usd,
                'account_type' => BankAccountType::SAVINGS,
                'current_balance' => 250000.00,
                'available_balance' => 250000.00,
                'last_reconciliation_date' => '2026-03-31',
                'last_reconciliation_balance' => 245000.00,
                'next_check_number' => null,
                'check_form_id' => null,
                'active' => true,
                'allow_payments' => false,
                'allow_receipts' => true,
            ],
            // Emergency Reserve - USD Savings
            [
                'account_code' => 'BANK-005',
                'account_name' => 'Emergency Reserve Fund',
                'bank_name' => 'Ally Bank',
                'bank_branch' => 'Online Banking',
                'account_number' => '002345678901',
                'routing_number' => '124003116',
                'swift_code' => null,
                'iban' => null,
                'gl_account_id' => $savingsGl,
                'currency_id' => $usd,
                'account_type' => BankAccountType::SAVINGS,
                'current_balance' => 500000.00,
                'available_balance' => 500000.00,
                'last_reconciliation_date' => '2026-03-31',
                'last_reconciliation_balance' => 500000.00,
                'next_check_number' => null,
                'check_form_id' => null,
                'active' => true,
                'allow_payments' => true,
                'allow_receipts' => true,
            ],
            // Money Market Account
            [
                'account_code' => 'BANK-006',
                'account_name' => 'Money Market Investment',
                'bank_name' => 'Goldman Sachs Bank USA',
                'bank_branch' => 'Marcus Branch',
                'account_number' => '003456789012',
                'routing_number' => '124085244',
                'swift_code' => 'GSCMUS33',
                'iban' => null,
                'gl_account_id' => $moneyMarketGl,
                'currency_id' => $usd,
                'account_type' => BankAccountType::MONEY_MARKET,
                'current_balance' => 750000.00,
                'available_balance' => 750000.00,
                'last_reconciliation_date' => '2026-03-31',
                'last_reconciliation_balance' => 740000.00,
                'next_check_number' => null,
                'check_form_id' => null,
                'active' => true,
                'allow_payments' => true,
                'allow_receipts' => true,
            ],
            // Certificate of Deposit - 12 Month
            [
                'account_code' => 'BANK-007',
                'account_name' => 'CD - 12 Month Term',
                'bank_name' => 'Citibank',
                'bank_branch' => 'Investment Services',
                'account_number' => '004567890123',
                'routing_number' => '021000089',
                'swift_code' => 'CITIUS33',
                'iban' => null,
                'gl_account_id' => $cdGl,
                'currency_id' => $usd,
                'account_type' => BankAccountType::CERTIFICATE_OF_DEPOSIT,
                'current_balance' => 100000.00,
                'available_balance' => 0.00, // Locked until maturity
                'last_reconciliation_date' => '2026-03-31',
                'last_reconciliation_balance' => 100000.00,
                'next_check_number' => null,
                'check_form_id' => null,
                'active' => true,
                'allow_payments' => false,
                'allow_receipts' => false,
            ],
            // Certificate of Deposit - 6 Month
            [
                'account_code' => 'BANK-008',
                'account_name' => 'CD - 6 Month Term',
                'bank_name' => 'Discover Bank',
                'bank_branch' => 'Online CD',
                'account_number' => '005678901234',
                'routing_number' => '031100649',
                'swift_code' => null,
                'iban' => null,
                'gl_account_id' => $cdGl,
                'currency_id' => $usd,
                'account_type' => BankAccountType::CERTIFICATE_OF_DEPOSIT,
                'current_balance' => 50000.00,
                'available_balance' => 0.00,
                'last_reconciliation_date' => '2026-03-31',
                'last_reconciliation_balance' => 50000.00,
                'next_check_number' => null,
                'check_form_id' => null,
                'active' => true,
                'allow_payments' => false,
                'allow_receipts' => false,
            ],
            // EUR Operating Account - Foreign Currency
            [
                'account_code' => 'BANK-EUR-001',
                'account_name' => 'EUR Operating Account',
                'bank_name' => 'Deutsche Bank AG',
                'bank_branch' => 'Frankfurt Main Branch',
                'account_number' => 'DE89370400440532013000',
                'routing_number' => null,
                'swift_code' => 'DEUTDEFF',
                'iban' => 'DE89370400440532013000',
                'gl_account_id' => $foreignEurGl,
                'currency_id' => $eur,
                'account_type' => BankAccountType::FOREIGN_CURRENCY,
                'current_balance' => 185000.00,
                'available_balance' => 185000.00,
                'last_reconciliation_date' => '2026-03-31',
                'last_reconciliation_balance' => 180000.00,
                'next_check_number' => null,
                'check_form_id' => null,
                'active' => true,
                'allow_payments' => true,
                'allow_receipts' => true,
            ],
            // GBP Operating Account - Foreign Currency
            [
                'account_code' => 'BANK-GBP-001',
                'account_name' => 'GBP Operating Account',
                'bank_name' => 'HSBC Bank plc',
                'bank_branch' => 'London City Branch',
                'account_number' => 'GB29NWBK60161331926819',
                'routing_number' => '400515',
                'swift_code' => 'HBUKGB4B',
                'iban' => 'GB29NWBK60161331926819',
                'gl_account_id' => $foreignGbpGl,
                'currency_id' => $gbp,
                'account_type' => BankAccountType::FOREIGN_CURRENCY,
                'current_balance' => 95000.00,
                'available_balance' => 95000.00,
                'last_reconciliation_date' => '2026-03-31',
                'last_reconciliation_balance' => 92000.00,
                'next_check_number' => null,
                'check_form_id' => null,
                'active' => true,
                'allow_payments' => true,
                'allow_receipts' => true,
            ],
            // CAD Operating Account - Foreign Currency
            [
                'account_code' => 'BANK-CAD-001',
                'account_name' => 'CAD Operating Account',
                'bank_name' => 'Royal Bank of Canada',
                'bank_branch' => 'Toronto Downtown',
                'account_number' => '00345678912',
                'routing_number' => '003',
                'swift_code' => 'ROYCCAT2',
                'iban' => null,
                'gl_account_id' => $foreignUsdGl,
                'currency_id' => $cad ?? $usd, // Fallback if CAD is not seeded
                'account_type' => BankAccountType::FOREIGN_CURRENCY,
                'current_balance' => 75000.00,
                'available_balance' => 75000.00,
                'last_reconciliation_date' => '2026-03-31',
                'last_reconciliation_balance' => 72000.00,
                'next_check_number' => '8001',
                'check_form_id' => 'CAD-01',
                'active' => true,
                'allow_payments' => true,
                'allow_receipts' => true,
            ],
            // Petty Cash Account (Treated as Bank Account)
            [
                'account_code' => 'CASH-001',
                'account_name' => 'Main Office Petty Cash',
                'bank_name' => 'Internal Cash Management',
                'bank_branch' => 'Head Office',
                'account_number' => 'PETTY-001',
                'routing_number' => null,
                'swift_code' => null,
                'iban' => null,
                'gl_account_id' => $checkingGl,
                'currency_id' => $usd,
                'account_type' => BankAccountType::CHECKING,
                'current_balance' => 2500.00,
                'available_balance' => 2500.00,
                'last_reconciliation_date' => '2026-03-31',
                'last_reconciliation_balance' => 2500.00,
                'next_check_number' => null,
                'check_form_id' => null,
                'active' => true,
                'allow_payments' => true,
                'allow_receipts' => true,
            ],
            // Inactive/Closed Account
            [
                'account_code' => 'BANK-OLD-001',
                'account_name' => 'Former Primary Account (Closed)',
                'bank_name' => 'Silicon Valley Bank',
                'bank_branch' => 'Tech Branch',
                'account_number' => 'CLOSED-001',
                'routing_number' => '121140399',
                'swift_code' => 'SIVBUS66',
                'iban' => null,
                'gl_account_id' => $checkingGl,
                'currency_id' => $usd,
                'account_type' => BankAccountType::CHECKING,
                'current_balance' => 0.00,
                'available_balance' => 0.00,
                'last_reconciliation_date' => '2023-03-10',
                'last_reconciliation_balance' => 0.00,
                'next_check_number' => null,
                'check_form_id' => null,
                'active' => false,
                'allow_payments' => false,
                'allow_receipts' => false,
            ],
        ];

        foreach ($bankAccounts as $account) {
            BankAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }

        $this->command->info('Bank Accounts seeded successfully!');
        $this->command->info('Total: '.count($bankAccounts).' accounts');

        $activeCount = collect($bankAccounts)->where('active', true)->count();
        $inactiveCount = collect($bankAccounts)->where('active', false)->count();
        $this->command->info("Active: {$activeCount}, Inactive: {$inactiveCount}");

        // Summary by type
        $byType = collect($bankAccounts)->groupBy('account_type.value');
        foreach ($byType as $type => $items) {
            $this->command->info("{$type}: ".$items->count());
        }

        // Summary by currency
        $byCurrency = collect($bankAccounts)->groupBy('currency_code');
        foreach ($byCurrency as $currency => $items) {
            $total = $items->sum('current_balance');
            $this->command->info("{$currency}: {$items->count()} accounts, Balance: ".number_format($total, 2));
        }
    }

    private function createRequiredGlAccounts(): void
    {
        $accounts = [
            [
                'account_number' => '10100',
                'name' => 'Bank - Checking Accounts',
                'account_type' => 'ASSET',
                'account_category' => 'CASH',
            ],
            [
                'account_number' => '10200',
                'name' => 'Bank - Savings Accounts',
                'account_type' => 'ASSET',
                'account_category' => 'CASH',
            ],
            [
                'account_number' => '10300',
                'name' => 'Bank - Money Market',
                'account_type' => 'ASSET',
                'account_category' => 'CASH',
            ],
            [
                'account_number' => '10400',
                'name' => 'Bank - Certificates of Deposit',
                'account_type' => 'ASSET',
                'account_category' => 'CASH',
            ],
            [
                'account_number' => '10500',
                'name' => 'Bank - Foreign Currency USD',
                'account_type' => 'ASSET',
                'account_category' => 'CASH',
            ],
            [
                'account_number' => '10510',
                'name' => 'Bank - Foreign Currency EUR',
                'account_type' => 'ASSET',
                'account_category' => 'CASH',
            ],
            [
                'account_number' => '10520',
                'name' => 'Bank - Foreign Currency GBP',
                'account_type' => 'ASSET',
                'account_category' => 'CASH',
            ],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_number' => $account['account_number']],
                $account
            );
        }
    }
}
