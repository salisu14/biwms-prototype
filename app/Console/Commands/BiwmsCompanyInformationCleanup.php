<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CompanyInformation;
use Illuminate\Console\Command;

final class BiwmsCompanyInformationCleanup extends Command
{
    protected $signature = 'biwms:company-information-cleanup
                            {--apply : Delete only the proven legacy placeholder candidates}';

    protected $description = 'Report, and optionally remove, unreferenced legacy Company Information placeholders.';

    public function handle(): int
    {
        $rows = CompanyInformation::query()
            ->whereNull('business_id')
            ->where('company_name', 'Your Company Name')
            ->orderBy('id')
            ->get();

        $candidates = $rows->filter(fn (CompanyInformation $row): bool => $this->isStrictPlaceholder($row));
        $ambiguous = $rows->reject(fn (CompanyInformation $row): bool => $this->isStrictPlaceholder($row));

        $this->info('Mode: '.($this->option('apply') ? 'apply' : 'dry-run').'.');
        $this->line('Strict, unreferenced placeholder candidates: '.$candidates->count());
        foreach ($candidates as $row) {
            $this->line(" - #{$row->id} {$row->company_name} (created {$row->created_at})");
        }

        if ($ambiguous->isNotEmpty()) {
            $this->warn('Ambiguous null-business Company Information rows were not selected: '.$ambiguous->count());
            foreach ($ambiguous as $row) {
                $this->line(" - #{$row->id} retained for review");
            }
        }

        if (! $this->option('apply')) {
            $this->comment('No rows changed. Re-run with --apply only after reviewing the listed candidates.');

            return self::SUCCESS;
        }

        $deleted = $candidates->each->delete()->count();
        $this->info("Deleted {$deleted} proven placeholder row(s).");

        return self::SUCCESS;
    }

    private function isStrictPlaceholder(CompanyInformation $row): bool
    {
        foreach ([
            'trading_name', 'registration_no', 'tax_registration_no', 'tax_office',
            'address_line_1', 'address_line_2', 'city', 'state_province', 'postal_code',
            'phone_no', 'mobile_no', 'email', 'website', 'contact_person_name',
            'contact_person_title', 'contact_person_phone', 'contact_person_email',
            'logo_path', 'favicon_path', 'bank_name', 'bank_account_no', 'bank_branch',
            'swift_code', 'reporting_currency_code', 'terms_conditions', 'invoice_footer',
        ] as $column) {
            if (filled($row->getAttribute($column))) {
                return false;
            }
        }

        return ($row->country_code ?? 'NGA') === 'NGA'
            && ($row->base_currency_code ?? 'NGN') === 'NGN'
            && ($row->fiscal_year_start_month ?? '01') === '01';
    }
}
