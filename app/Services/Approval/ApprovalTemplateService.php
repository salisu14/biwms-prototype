<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Contracts\Approvable;
use App\Models\ApprovalTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalTemplateService
{
    /**
     * Get approvers for a document based on approval templates.
     */
    public function getApproversForDocument(Approvable $model): array
    {
        // Find matching approval template
        $template = $this->findMatchingTemplate($model);

        if (! $template) {
            return []; // No approval required
        }

        return $this->resolveApprovers($template, $model);
    }

    /**
     * Find approval template matching document criteria.
     */
    private function findMatchingTemplate(Approvable $model): ?ApprovalTemplate
    {
        return ApprovalTemplate::where('enabled', true)
            ->where('document_type', $model->getApprovalDocumentType())
            ->where(function ($q) use ($model) {
                // Check amount limits
                $q->whereNull('amount_limit')
                    ->orWhere('amount_limit', '>=', $model->getApprovalAmount());
            })
            ->where(function ($q) use ($model) {
                // Check posting group filter
                $postingGroupId = $model->getApprovalPostingGroupId();
                $q->whereNull('vendor_posting_group_filter')
                    ->orWhere('vendor_posting_group_filter', $postingGroupId);
            })
            ->where(function ($q) use ($model) {
                // Check dimension filters if any (using first dimension for now as per legacy)
                $dimensions = $model->getApprovalDimensions();
                $dim1 = $dimensions['shortcut_dimension_1_code'] ?? $model->shortcut_dimension_1_code ?? null;
                
                if ($dim1) {
                    $q->whereJsonContains('dimension_1_filter', $dim1)
                        ->orWhereNull('dimension_1_filter');
                }
            })
            ->orderBy('amount_limit', 'asc') // Most restrictive first
            ->first();
    }

    /**
     * Resolve approver users from template entries.
     */
    private function resolveApprovers(ApprovalTemplate $template, Approvable $model): array
    {
        $approvers = [];
        $entries = $template->entries()->orderBy('sequence_no')->get();
        $requestorId = $model->getApprovalRequestorId();

        foreach ($entries as $entry) {
            $user = match ($entry->approver_type) {
                'user' => User::find($entry->approver_id),
                'role' => $this->getUserByRole($entry->approver_role, $model),
                'hierarchy' => $this->getApproverFromHierarchy($model, $entry->hierarchy_levels),
                'dimension' => $this->getApproverFromDimension($model, $entry->dimension_code),
                default => null,
            };

            if ($user && $user->id !== $requestorId) { // Can't approve own document
                $approvers[] = $user;
            }
        }

        // Remove duplicates while preserving order
        return collect($approvers)->unique('id')->values()->all();
    }

    /**
     * Get user by role.
     */
    private function getUserByRole(string $role, Approvable $model): ?User
    {
        $locationCode = $model->getApprovalLocationCode();
        
        $query = User::whereHas('roles', function ($q) use ($role) {
            $q->where('name', $role);
        });

        if ($locationCode) {
            $query->where('location_code', $locationCode);
        }

        return $query->first();
    }

    /**
     * Get approver from approval hierarchy.
     */
    private function getApproverFromHierarchy(Approvable $model, int $levels): ?User
    {
        $requestor = User::find($model->getApprovalRequestorId());

        if (! $requestor) {
            return null;
        }

        // Walk up the hierarchy
        $current = $requestor;
        for ($i = 0; $i < $levels; $i++) {
            $manager = $current->manager; // Assumes User has manager relationship

            if (! $manager) {
                return null;
            }
            $current = $manager;
        }

        return $current;
    }

    /**
     * Get approver from dimension value.
     */
    private function getApproverFromDimension(Approvable $model, string $dimensionCode): ?User
    {
        $dimensions = $model->getApprovalDimensions();
        $dimensionValue = $dimensions[$dimensionCode] ?? null;

        if (! $dimensionValue) {
            return null;
        }

        // Lookup dimension value table for approver
        $approverId = DB::table('dimension_values')
            ->where('code', $dimensionCode)
            ->where('dimension_value', $dimensionValue)
            ->value('approver_user_id');

        return $approverId ? User::find($approverId) : null;
    }

    /**
     * Check if document requires approval.
     */
    public function requiresApproval(Approvable $model): bool
    {
        return ! empty($this->getApproversForDocument($model));
    }
}
