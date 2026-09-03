<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Services\Finance\BankOpeningBalanceRepairService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('biwms:bank-opening-balance-repair {--bank-account= : Bank account ID} {--dry-run : Report the proposed correction without mutating data} {--apply : Apply the controlled append-only correction}')]
#[Description('Inspect or apply a controlled correction for a known same-account bank opening-balance G/L defect.')]
class BiwmsBankOpeningBalanceRepair extends Command
{
    public function __construct(private readonly BankOpeningBalanceRepairService $repairService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Choose either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        $bankAccountId = $this->option('bank-account');
        if (! is_numeric($bankAccountId)) {
            $this->error('Provide a bank account ID with --bank-account={id}.');

            return self::FAILURE;
        }

        $bankAccount = BankAccount::query()->find((int) $bankAccountId);
        if (! $bankAccount) {
            $this->error('Bank account not found.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        try {
            $result = $apply
                ? $this->repairService->repair($bankAccount)
                : $this->repairService->analyze($bankAccount);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('BIWMS Bank Opening Balance Repair');
        $this->line($apply ? 'Mode: apply. Controlled correction was requested.' : 'Mode: dry-run. No data was changed.');
        $this->line('Original document: '.$result['original_document_number']);
        $this->line('Original posting transaction: '.$result['original_transaction_id']);
        $this->line('Bank G/L account: '.$result['bank_gl_account_id']);
        $this->line('Opening Balance Equity account: '.$result['equity_account_id']);
        $this->line('Amount: '.$result['amount'].' '.$result['currency_code']);
        $this->line('Proposed correction: Dr bank / Cr Opening Balance Equity');
        $this->line('Correction idempotency key: '.$result['correction_key']);

        if ($result['correction_exists']) {
            $this->warn('A correction already exists; no duplicate correction is required.');
        }

        if ($apply && ! ($result['repaired'] ?? false) && ! ($result['idempotent'] ?? false)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
