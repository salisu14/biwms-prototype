<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\RecruitmentApplication;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentOffer;
use App\Models\RecruitmentOnboardingPlan;
use App\Models\RecruitmentRequisition;
use App\Models\RecruitmentVacancy;
use Filament\Pages\Page;

class RecruitmentDashboard extends Page
{
    protected string $view = 'filament.pages.recruitment-dashboard';

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'openRequisitions' => RecruitmentRequisition::query()->whereIn('status', ['draft', 'open', 'submitted', 'approved'])->count(),
            'openVacancies' => RecruitmentVacancy::query()->whereIn('status', ['open', 'published', 'active'])->count(),
            'activeCandidates' => RecruitmentCandidate::query()->whereNotIn('status', ['blacklisted', 'hired'])->count(),
            'activeApplications' => RecruitmentApplication::query()->whereNotIn('status', ['rejected', 'withdrawn', 'hired'])->count(),
            'pendingOffers' => RecruitmentOffer::query()->whereIn('status', ['draft', 'approved', 'issued'])->count(),
            'activeOnboardingPlans' => RecruitmentOnboardingPlan::query()->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'recentApplications' => RecruitmentApplication::query()
                ->with(['candidate', 'vacancy'])
                ->latest('application_date')
                ->limit(8)
                ->get(),
            'recentOffers' => RecruitmentOffer::query()
                ->with('application.candidate')
                ->latest('updated_at')
                ->limit(6)
                ->get(),
        ];
    }
}
