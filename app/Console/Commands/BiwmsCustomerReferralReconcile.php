<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CustomerReferralStatus;
use App\Enums\ReferrerType;
use App\Models\CustomerReferral;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('biwms:customer-referral-reconcile {--details : Show detailed findings} {--business= : Limit by business id} {--customer= : Limit by customer id} {--referrer= : Limit by referrer id} {--export= : Write JSON findings to a file path}')]
#[Description('Report Customer Referral integrity findings without mutating data.')]
class BiwmsCustomerReferralReconcile extends Command
{
    public function handle(): int
    {
        $findings = $this->findings();

        $this->info('BIWMS Customer Referral Reconcile');
        $this->line('Findings: '.count($findings));

        if ($this->option('details')) {
            foreach ($findings as $finding) {
                $this->line(sprintf(
                    '[%s] referral=%s customer=%s referrer=%s %s',
                    $finding['type'],
                    $finding['referral_id'] ?? '—',
                    $finding['customer_id'] ?? '—',
                    $finding['referrer_id'] ?? '—',
                    $finding['message'],
                ));
            }
        }

        if ($path = $this->option('export')) {
            File::ensureDirectoryExists(dirname((string) $path));
            File::put((string) $path, json_encode($findings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->info("Exported findings to {$path}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findings(): array
    {
        $query = CustomerReferral::query()->with(['customer', 'referrer']);

        if ($this->option('business')) {
            $query->where('business_id', $this->option('business'));
        }

        if ($this->option('customer')) {
            $query->where('customer_id', $this->option('customer'));
        }

        if ($this->option('referrer')) {
            $query->where('referrer_id', $this->option('referrer'));
        }

        $referrals = $query->get();
        $findings = [];

        $referrals
            ->where('status', CustomerReferralStatus::ACTIVE)
            ->where('is_primary', true)
            ->whereNull('effective_to')
            ->groupBy('customer_id')
            ->filter(fn ($rows): bool => $rows->count() > 1)
            ->each(function ($rows, $customerId) use (&$findings): void {
                $findings[] = [
                    'type' => 'multiple_active_primary_referrals',
                    'severity' => 'critical',
                    'customer_id' => $customerId,
                    'message' => 'Customer has more than one open active primary referral.',
                ];
            });

        foreach ($referrals as $referral) {
            if ($referral->status === CustomerReferralStatus::ACTIVE && $referral->referrer && ! $referral->referrer->is_active) {
                $findings[] = $this->finding('active_referral_inactive_referrer', $referral, 'Active referral is linked to an inactive Referrer.');
            }

            if ($referral->status === CustomerReferralStatus::ACTIVE && $referral->effective_to && $referral->effective_to->isPast()) {
                $findings[] = $this->finding('active_referral_past_effective_to', $referral, 'Active referral has an effective_to date in the past.');
            }

            if ($referral->status === CustomerReferralStatus::ENDED && $referral->effective_to === null) {
                $findings[] = $this->finding('ended_referral_missing_effective_to', $referral, 'Ended referral is missing effective_to.');
            }

            if ($referral->status === CustomerReferralStatus::CANCELLED && $referral->effective_to === null && $referral->cancelled_at === null) {
                $findings[] = $this->finding('cancelled_referral_missing_cancel_metadata', $referral, 'Cancelled referral is missing cancellation metadata.');
            }

            if ($referral->referrer?->type === ReferrerType::EXISTING_CUSTOMER && (int) $referral->referrer->customer_id === (int) $referral->customer_id) {
                $findings[] = $this->finding('self_referral', $referral, 'Customer is referred by itself.');
            }

            $customerBusinessId = data_get($referral->customer, 'business_id');
            if ($customerBusinessId !== null && $referral->referrer?->business_id !== null && (int) $customerBusinessId !== (int) $referral->referrer->business_id) {
                $findings[] = $this->finding('cross_business_referral', $referral, 'Customer and Referrer business scopes do not match.');
            }
        }

        return $findings;
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(string $type, CustomerReferral $referral, string $message): array
    {
        return [
            'type' => $type,
            'severity' => 'critical',
            'referral_id' => $referral->id,
            'customer_id' => $referral->customer_id,
            'referrer_id' => $referral->referrer_id,
            'message' => $message,
        ];
    }
}
