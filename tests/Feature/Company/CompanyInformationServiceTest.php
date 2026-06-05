<?php

use App\Models\CompanyInformation;
use App\Services\Company\CompanyInformationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('prefers trading name for report headers and invoice footers', function () {
    Schema::create('company_information', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('business_id')->nullable();
        $table->string('company_name')->nullable();
        $table->string('trading_name')->nullable();
        $table->string('registration_no')->nullable();
        $table->string('tax_registration_no')->nullable();
        $table->string('country_code')->nullable();
        $table->string('phone_no')->nullable();
        $table->string('email')->nullable();
        $table->string('website')->nullable();
        $table->string('logo_path')->nullable();
        $table->string('favicon_path')->nullable();
        $table->string('base_currency_code')->nullable();
        $table->string('fiscal_year_start_month')->nullable();
        $table->text('invoice_footer')->nullable();
        $table->string('address_line_1')->nullable();
        $table->string('address_line_2')->nullable();
        $table->string('city')->nullable();
        $table->string('state_province')->nullable();
        $table->string('postal_code')->nullable();
        $table->timestamps();
    });

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
