<?php

use App\Models\Business;
use App\Models\CompanyInformation;
use App\Services\Company\CompanyInformationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('prefers trading name for report headers and invoice footers', function () {
    session()->flush();
    request()->query->remove('business_id');
    request()->request->remove('business_id');

    $business = Business::query()->updateOrCreate(
        ['code' => 'TEST-CO'],
        ['name' => 'Test Company', 'is_active' => true]
    );

    session(['active_business_id' => $business->id]);

    CompanyInformation::getInstance($business->id)->update([
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

it('resolves report identity from the active business without crossing businesses', function (): void {
    $businessA = Business::query()->create(['code' => 'BIZ-A', 'name' => 'Business A', 'is_active' => true]);
    $businessB = Business::query()->create(['code' => 'BIZ-B', 'name' => 'Business B', 'is_active' => true]);

    CompanyInformation::query()->create([
        'business_id' => $businessA->id,
        'company_name' => 'Company A',
        'country_code' => 'NGA',
    ]);
    CompanyInformation::query()->create([
        'business_id' => $businessB->id,
        'company_name' => 'Company B',
        'country_code' => 'NGA',
    ]);

    session(['active_business_id' => $businessA->id]);

    expect(app(CompanyInformationService::class)->getReportHeader($businessB->id)['legal_name'])
        ->toBe('Company B');

    session(['active_business_id' => $businessB->id]);

    expect(app(CompanyInformationService::class)->getReportHeader()['legal_name'])
        ->toBe('Company B');
});

it('does not create a placeholder report identity when a business profile is missing', function (): void {
    $business = Business::query()->create(['code' => 'BIZ-MISSING', 'name' => 'Missing Profile', 'is_active' => true]);
    session(['active_business_id' => $business->id]);

    expect(fn () => app(CompanyInformationService::class)->getReportHeader())
        ->toThrow(RuntimeException::class, 'Company Information has not been configured');
});

it('fails safely without business context and does not read null-business placeholders', function (): void {
    session()->forget('active_business_id');
    CompanyInformation::query()->create([
        'business_id' => null,
        'company_name' => 'Legacy Placeholder',
        'country_code' => 'NGA',
    ]);
    $before = CompanyInformation::query()->count();

    expect(fn () => app(CompanyInformationService::class)->getReportHeader())
        ->toThrow(RuntimeException::class, 'Business context is required')
        ->and(CompanyInformation::query()->count())->toBe($before);
});

it('embeds a configured logo from the public disk without creating another profile', function (): void {
    Storage::fake('public');
    $business = Business::query()->create(['code' => 'BIZ-LOGO', 'name' => 'Logo Business', 'is_active' => true]);
    $company = CompanyInformation::query()->create([
        'business_id' => $business->id,
        'company_name' => 'Logo Company',
        'country_code' => 'NGA',
        'logo_path' => 'company/logos/logo.png',
    ]);
    Storage::disk('public')->put($company->logo_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    session(['active_business_id' => $business->id]);

    $header = app(CompanyInformationService::class)->getReportHeader();

    expect($header['logo_data_uri'])->toStartWith('data:image/png;base64,')
        ->and(CompanyInformation::query()->where('business_id', $business->id)->count())->toBe(1);
});

it('initializes at most one business-owned profile', function (): void {
    $business = Business::query()->create(['code' => 'BIZ-ONE', 'name' => 'One Profile Business', 'is_active' => true]);
    session(['active_business_id' => $business->id]);
    $service = app(CompanyInformationService::class);

    $service->update(['company_name' => 'First Identity']);
    $service->update(['company_name' => 'Updated Identity']);

    expect(CompanyInformation::query()->where('business_id', $business->id)->count())->toBe(1)
        ->and(CompanyInformation::query()->where('business_id', $business->id)->value('company_name'))->toBe('Updated Identity');
});

it('cleanup command dry-run identifies only strict placeholders and changes nothing', function (): void {
    $placeholder = CompanyInformation::query()->create([
        'business_id' => null,
        'company_name' => 'Your Company Name',
        'country_code' => 'NGA',
        'base_currency_code' => 'NGN',
        'fiscal_year_start_month' => '01',
    ]);
    $ambiguous = CompanyInformation::query()->create([
        'business_id' => null,
        'company_name' => 'Your Company Name',
        'country_code' => 'NGA',
        'email' => 'retained@example.test',
    ]);
    $before = CompanyInformation::query()->count();

    $this->artisan('biwms:company-information-cleanup')
        ->expectsOutputToContain("#{$placeholder->id}")
        ->expectsOutputToContain("#{$ambiguous->id} retained for review")
        ->assertExitCode(0);

    expect(CompanyInformation::query()->count())->toBe($before)
        ->and(CompanyInformation::query()->find($placeholder->id))->not->toBeNull();
});
