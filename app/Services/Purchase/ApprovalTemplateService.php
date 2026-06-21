<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\ApprovalTemplate;
use App\Models\PurchaseQuote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalTemplateService
{
    /**
     * Get approvers for a quote based on approval templates (BC: Approval Templates)
     */
    public function getApproversForQuote(PurchaseQuote $quote): array
    {
        // Find matching approval template
        $template = $this->findMatchingTemplate($quote);

        if (! $template) {
            return []; // No approval required
        }

        return $this->resolveApprovers($template, $quote);
    }

    /**
     * Find approval template matching quote criteria
     */
    private function findMatchingTemplate(PurchaseQuote $quote): ?ApprovalTemplate
    {
        return ApprovalTemplate::where('enabled', true)
            ->where(function ($q) use ($quote) {
                // Check amount limits
                $q->whereNull('amount_limit')
                    ->orWhere('amount_limit', '>=', $quote->amount_including_vat);
            })
            ->where(function ($q) use ($quote) {
                // Check vendor posting group filter
                $q->whereNull('vendor_posting_group_filter')
                    ->orWhere('vendor_posting_group_filter', $quote->vendor->vendor_posting_group_id);
            })
            ->where(function ($q) use ($quote) {
                // Check dimension filters if any
                if ($quote->shortcut_dimension_1_code) {
                    $q->whereJsonContains('dimension_1_filter', $quote->shortcut_dimension_1_code)
                        ->orWhereNull('dimension_1_filter');
                }
            })
            ->orderBy('amount_limit', 'asc') // Most restrictive first
            ->first();
    }

    /**
     * Resolve approver users from template entries
     */
    private function resolveApprovers(ApprovalTemplate $template, PurchaseQuote $quote): array
    {
        $approvers = [];
        $entries = $template->entries()->orderBy('sequence_no')->get();

        foreach ($entries as $entry) {
            $user = match ($entry->approver_type) {
                'user' => User::find($entry->approver_id),
                'role' => $this->getUserByRole($entry->approver_role, $quote),
                'hierarchy' => $this->getApproverFromHierarchy($quote, $entry->hierarchy_levels),
                'dimension' => $this->getApproverFromDimension($quote, $entry->dimension_code),
                default => null,
            };

            if ($user && $user->id !== $quote->buyer_id) { // Can't approve own quote
                $approvers[] = $user;
            }
        }

        // Remove duplicates while preserving order
        return collect($approvers)->unique('id')->values()->all();
    }

    /**
     * Get user by role (e.g., 'purchasing_manager')
     */
    private function getUserByRole(string $role, PurchaseQuote $quote): ?User
    {
        // Implementation depends on your role system
        return User::whereHas('roles', function ($q) use ($role) {
            $q->where('name', $role);
        })->where('location_code', $quote->location_code) // Respect location
            ->first();
    }

    /**
     * Get approver from approval hierarchy
     */
    private function getApproverFromHierarchy(PurchaseQuote $quote, int $levels): ?User
    {
        $requestor = $quote->buyer;

        if (! $requestor) {
            return null;
        }

        // Walk up the hierarchy
        $current = $requestor;
        for ($i = 0; $i < $levels; $i++) {
            $manager = $current->manager; // Assumes User has manager relationship

            if (! $manager) {
                return $current; // Return highest available
            }
            $current = $manager;
        }

        return $current;
    }

    /**
     * Get approver from dimension value (e.g., Department Head)
     */
    private function getApproverFromDimension(PurchaseQuote $quote, string $dimensionCode): ?User
    {
        $dimensionValue = $quote->dimensions[$dimensionCode] ?? null;

        if (! $dimensionValue) {
            return null;
        }

        // Lookup dimension value table for approver
        return DB::table('dimension_values')
            ->where('code', $dimensionCode)
            ->where('dimension_value', $dimensionValue)
            ->value('approver_user_id');
    }

    /**
     * Check if quote amount requires approval
     */
    public function requiresApproval(PurchaseQuote $quote): bool
    {
        return ! empty($this->getApproversForQuote($quote));
    }

    /**
     * Get approval limit for user
     */
    public function getUserApprovalLimit(User $user, ?string $locationCode = null): float
    {
        $template = ApprovalTemplate::where('enabled', true)
            ->whereHas('entries', function ($q) use ($user) {
                $q->where('approver_type', 'user')
                    ->where('approver_id', $user->id);
            })
            ->where(function ($q) use ($locationCode) {
                $q->whereNull('location_filter')
                    ->orWhere('location_filter', $locationCode);
            })
            ->orderBy('amount_limit', 'desc')
            ->first();

        return $template?->amount_limit ?? 0;
    }
}
