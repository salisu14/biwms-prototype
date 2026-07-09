<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\AuditTrailService;
use App\Services\Hr\EmployeeIdCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class EmployeeIdCardController extends Controller
{
    public function __construct(
        private readonly EmployeeIdCardService $idCardService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function preview(Employee $employee): Response
    {
        abort_unless(auth()->user()?->can('hr.employee_id_card.view'), 403);
        abort_unless($this->hasIssuedCard($employee), 404);

        return response()->view('hr.employee-id-card', [
            'cards' => [$this->idCardService->cardViewData($employee)],
            'print' => false,
        ]);
    }

    public function print(Employee $employee): Response
    {
        abort_unless(auth()->user()?->can('hr.employee_id_card.view'), 403);
        abort_unless($this->hasIssuedCard($employee), 404);

        return response()->view('hr.employee-id-card', [
            'cards' => [$this->idCardService->cardViewData($employee)],
            'print' => true,
        ]);
    }

    public function download(Employee $employee)
    {
        abort_unless(auth()->user()?->can('hr.employee_id_card.download'), 403);
        abort_unless($this->hasIssuedCard($employee), 404);

        $cardData = $this->idCardService->cardViewData($employee);

        $this->auditTrailService->recordGeneric(
            eventType: 'hr_id_card',
            action: 'card_downloaded',
            auditable: $cardData['employee'],
            documentType: 'EMPLOYEE_ID_CARD',
            documentNo: $cardData['employee']->id_card_number,
            description: "Downloaded employee ID card for {$cardData['employee']->employee_number}.",
            metadata: [
                'employee_number' => $cardData['employee']->employee_number,
                'id_card_number' => $cardData['employee']->id_card_number,
            ],
        );

        return Pdf::loadView('hr.employee-id-card', [
            'cards' => [$cardData],
            'print' => false,
        ])
            ->setPaper('a4', 'portrait')
            ->download($this->fileName($cardData['employee']));
    }

    public function bulkDownload()
    {
        abort_unless(auth()->user()?->can('hr.employee_id_card.download'), 403);
        abort_unless(auth()->user()?->can('hr.employee_id_card.generate'), 403);

        $ids = collect(explode(',', (string) request('ids')))
            ->filter()
            ->map(fn (string $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        abort_if($ids->isEmpty(), 404);

        /** @var Collection<int, Employee> $employees */
        $employees = Employee::query()
            ->with('department')
            ->whereKey($ids)
            ->orderBy('employee_number')
            ->get();

        abort_if($employees->isEmpty(), 404);

        $cards = $this->idCardService->cardViewDataForEmployees($employees);

        foreach ($cards as $card) {
            /** @var Employee $employee */
            $employee = $card['employee'];

            $this->auditTrailService->recordGeneric(
                eventType: 'hr_id_card',
                action: 'card_downloaded',
                auditable: $employee,
                documentType: 'EMPLOYEE_ID_CARD',
                documentNo: $employee->id_card_number,
                description: "Downloaded employee ID card for {$employee->employee_number}.",
                metadata: [
                    'employee_number' => $employee->employee_number,
                    'id_card_number' => $employee->id_card_number,
                    'bulk' => true,
                ],
            );
        }

        return Pdf::loadView('hr.employee-id-card', [
            'cards' => $cards,
            'print' => false,
        ])
            ->setPaper('a4', 'portrait')
            ->download('employee-id-cards.pdf');
    }

    public function verify(string $token): Response
    {
        $employee = Employee::query()
            ->with('department')
            ->where('id_card_token', $token)
            ->first();

        if ($employee === null || ! $this->idCardService->isVerifiable($employee)) {
            return response()->view('hr.employee-card-verify', [
                'employee' => null,
                'company' => $this->idCardService->companyInformation(),
                'photoUrl' => null,
                'isValid' => false,
            ], 404);
        }

        $this->auditTrailService->recordGeneric(
            eventType: 'hr_id_card',
            action: 'card_verified',
            auditable: $employee,
            documentType: 'EMPLOYEE_ID_CARD',
            documentNo: $employee->id_card_number,
            description: "Verified employee ID card for {$employee->employee_number}.",
            metadata: [
                'employee_number' => $employee->employee_number,
                'id_card_number' => $employee->id_card_number,
            ],
        );

        return response()->view('hr.employee-card-verify', [
            'employee' => $employee,
            'company' => $this->idCardService->companyInformation(),
            'photoUrl' => $this->idCardService->cardViewData($employee)['photoUrl'],
            'isValid' => true,
        ]);
    }

    private function fileName(Employee $employee): string
    {
        return str($employee->employee_number ?: 'employee')
            ->slug()
            ->append('-id-card.pdf')
            ->toString();
    }

    private function hasIssuedCard(Employee $employee): bool
    {
        return filled($employee->id_card_number) && filled($employee->id_card_token);
    }
}
