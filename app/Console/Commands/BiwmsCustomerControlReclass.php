<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use App\Models\SubledgerOpeningBalance;
use App\Services\Finance\CustomerControlAccountReclassificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('biwms:customer-control-reclass {document : Historical customer opening-balance document number} {--target-account=11100 : Correct receivables account number} {--dry-run : Inspect without mutating data} {--apply : Apply the controlled append-only correction} {--actor= : User ID for audit attribution}')]
#[Description('Inspect or apply a controlled historical customer receivables account reclassification.')]
class BiwmsCustomerControlReclass extends Command
{
    public function __construct(private readonly CustomerControlAccountReclassificationService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Choose either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        $opening = SubledgerOpeningBalance::query()->where('document_number', $this->argument('document'))->first();
        $target = ChartOfAccount::query()->where('account_number', $this->option('target-account'))->first();
        if (! $opening || ! $target) {
            $this->error('The opening balance document or target account could not be found.');

            return self::FAILURE;
        }

        try {
            $result = $this->option('apply')
                ? $this->service->reclassifyCustomerReceivables(
                    $opening,
                    $target,
                    $this->option('actor') !== null ? (int) $this->option('actor') : null,
                )
                : $this->service->analyzeCustomerReceivables($opening, $target);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('BIWMS Customer Receivables Reclassification');
        $this->line($this->option('apply') ? 'Mode: apply. Controlled correction was requested.' : 'Mode: dry-run. No data was changed.');
        $this->line('Original document: '.$result['original_document_number']);
        $this->line('Original account: '.$result['original_account_id']);
        $this->line('Corrected account: '.$result['target_account_id']);
        $this->line('Amount: '.$result['amount']);
        $this->line('Correction idempotency key: '.$result['correction_key']);

        if ($result['idempotent'] ?? false) {
            $this->warn('A correction already exists; no duplicate correction was required.');
        }

        return self::SUCCESS;
    }
}
