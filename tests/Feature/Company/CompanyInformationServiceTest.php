<?php

use App\Models\CompanyInformation;
use App\Services\Company\CompanyInformationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prefers trading name for report headers and invoice footers', function () {
    CompanyInformation::getInstance()->update([
        'company_name' => 'Bifli Global Resources Ltd',
        'trading_name' => 'Bifli Living',
        'phone_no' => '08012345678',
        'email' => 'hello@bifli.test',
        'tax_registration_no' => 'TIN-001',
    ]);

    $service = app(CompanyInformationService::class);
    $header = $service->getReportHeader();
    $footer = $service->getInvoiceFooter();

    expect($header['name'])->toBe('Bifli Living')
        ->and($header['legal_name'])->toBe('Bifli Global Resources Ltd')
        ->and($header['trading_name'])->toBe('Bifli Living')
        ->and($footer)->toStartWith('Bifli Living');
});
