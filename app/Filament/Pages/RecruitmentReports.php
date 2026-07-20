<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\RecruitmentApplication;
use App\Models\RecruitmentOffer;
use App\Models\RecruitmentVacancy;
use Filament\Pages\Page;

class RecruitmentReports extends Page
{
    protected string $view = 'filament.pages.recruitment-reports';

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $from = today()->subDays(30);
        $until = today();

        return [
            'from' => $from,
            'until' => $until,
            'applicationsByStage' => RecruitmentApplication::query()
                ->selectRaw('current_stage, count(*) as aggregate')
                ->groupBy('current_stage')
                ->orderBy('current_stage')
                ->pluck('aggregate', 'current_stage')
                ->all(),
            'applicationsByStatus' => RecruitmentApplication::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->orderBy('status')
                ->pluck('aggregate', 'status')
                ->all(),
            'vacanciesByStatus' => RecruitmentVacancy::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->orderBy('status')
                ->pluck('aggregate', 'status')
                ->all(),
            'offersByStatus' => RecruitmentOffer::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->orderBy('status')
                ->pluck('aggregate', 'status')
                ->all(),
            'newApplications' => RecruitmentApplication::query()
                ->whereBetween('application_date', [$from, $until])
                ->count(),
        ];
    }
}
