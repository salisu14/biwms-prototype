<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeIdCard;
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
        $card = $this->idCardService->activeCardForEmployee($employee);
        abort_unless($card, 404);

        return response()->view('hr.employee-id-card', [
            'cards' => [$this->idCardService->cardViewData($card)],
            'print' => false,
        ]);
    }

    public function print(Employee $employee): Response
    {
        abort_unless(auth()->user()?->can('hr.employee_id_card.view'), 403);
        $card = $this->idCardService->activeCardForEmployee($employee);
        abort_unless($card, 404);

        $this->idCardService->markPrinted($card);

        return response()->view('hr.employee-id-card', [
            'cards' => [$this->idCardService->cardViewData($card)],
            'print' => true,
        ]);
    }

    public function download(Employee $employee)
    {
        abort_unless(auth()->user()?->can('hr.employee_id_card.download'), 403);
        $card = $this->idCardService->activeCardForEmployee($employee);
        abort_unless($card, 404);

        $card = $this->idCardService->markPrinted($card);
        $cardData = $this->idCardService->cardViewData($card, forPdf: true);

        $this->auditTrailService->recordGeneric(
            eventType: 'hr_id_card',
            action: 'card_downloaded',
            auditable: $card,
            documentType: 'EMPLOYEE_ID_CARD',
            documentNo: $card->card_number,
            description: "Downloaded employee ID card for {$card->employee?->employee_number}.",
            metadata: [
                'employee_number' => $card->employee?->employee_number,
                'card_number' => $card->card_number,
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

        $issuedCards = $employees->map(fn (Employee $employee) => $this->idCardService->ensureIssued($employee));
        $batch = $this->idCardService->createPrintBatch($issuedCards);
        $cards = $this->idCardService->cardViewDataForEmployees($issuedCards, forPdf: true);

        foreach ($cards as $card) {
            /** @var EmployeeIdCard $idCard */
            $idCard = $card['card'];
            $this->idCardService->markPrinted($idCard);

            $this->auditTrailService->recordGeneric(
                eventType: 'hr_id_card',
                action: 'card_downloaded',
                auditable: $idCard,
                documentType: 'EMPLOYEE_ID_CARD',
                documentNo: $idCard->card_number,
                description: "Downloaded employee ID card for {$idCard->employee?->employee_number}.",
                metadata: [
                    'employee_number' => $idCard->employee?->employee_number,
                    'card_number' => $idCard->card_number,
                    'batch_number' => $batch->batch_number,
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
        $card = $this->idCardService->verifyCardToken($token);

        if ($card === null) {
            return response()->view('hr.employee-card-verify', [
                'employee' => null,
                'company' => $this->idCardService->companyInformation(),
                'photoUrl' => null,
                'isValid' => false,
            ], 404);
        }

        $employee = $card->employee;

        $this->auditTrailService->recordGeneric(
            eventType: 'hr_id_card',
            action: 'card_verified',
            auditable: $card,
            documentType: 'EMPLOYEE_ID_CARD',
            documentNo: $card->card_number,
            description: "Verified employee ID card for {$employee->employee_number}.",
            metadata: [
                'employee_number' => $employee->employee_number,
                'card_number' => $card->card_number,
            ],
        );

        return response()->view('hr.employee-card-verify', [
            'employee' => $employee,
            'company' => $this->idCardService->companyInformation(),
            'photoUrl' => $this->idCardService->cardViewData($card)['photoUrl'],
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
}
