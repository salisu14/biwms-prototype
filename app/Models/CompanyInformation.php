<?php

namespace App\Models;

use App\Enums\CountryCode;
use App\Enums\CurrencyCode;
use App\Enums\FiscalMonth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyInformation extends Model
{
    use HasFactory;

    protected $table = 'company_information';

    const SINGLETON_ID = 1;

    protected $fillable = [
        'business_id',
        'company_name',
        'trading_name',
        'registration_no',
        'tax_registration_no',
        'tax_office',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'country_code',
        'phone_no',
        'mobile_no',
        'email',
        'website',
        'contact_person_name',
        'contact_person_title',
        'contact_person_phone',
        'contact_person_email',
        'logo_path',
        'favicon_path',
        'bank_name',
        'bank_account_no',
        'bank_branch',
        'swift_code',
        'fiscal_year_start_month',
        'base_currency_code',
        'reporting_currency_code',
        'terms_conditions',
        'invoice_footer',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function getInstance(?int $businessId = null): self
    {
        $resolvedBusinessId = $businessId ?? self::resolveCurrentBusinessId();

        if ($resolvedBusinessId !== null) {
            return self::query()->firstOrCreate(
                ['business_id' => $resolvedBusinessId],
                [
                    'company_name' => 'Your Company Name',
                    'country_code' => 'NGA',
                    'base_currency_code' => 'NGN',
                    'fiscal_year_start_month' => '01',
                ]
            );
        }

        return self::query()->firstOrCreate(
            ['id' => self::SINGLETON_ID, 'business_id' => null],
            [
                'id' => self::SINGLETON_ID,
                'company_name' => 'Your Company Name',
                'country_code' => 'NGA',
                'base_currency_code' => 'NGN',
                'fiscal_year_start_month' => '01',
            ]
        );
    }

    public static function updateInstance(array $data, ?int $businessId = null): self
    {
        $instance = self::getInstance($businessId);
        $instance->update($data);

        return $instance->fresh();
    }

    public static function get(string $field, mixed $default = null): mixed
    {
        return self::getInstance()->{$field} ?? $default;
    }

    public static function getCompanyName(): string
    {
        return self::get('company_name', 'Unnamed Company');
    }

    public static function getFullAddress(): string
    {
        $instance = self::getInstance();
        $lines = array_filter([
            $instance->address_line_1,
            $instance->address_line_2,
            $instance->city,
            $instance->state_province,
            $instance->postal_code,
            $instance->country?->label(),
        ]);

        return implode(', ', $lines);
    }

    public static function getAddressLines(): array
    {
        $instance = self::getInstance();

        return array_filter([
            $instance->address_line_1,
            $instance->address_line_2,
            trim("{$instance->city}, {$instance->state_province} {$instance->postal_code}"),
            $instance->country?->label(),
        ]);
    }

    public static function getLogoUrl(): ?string
    {
        $path = self::normalizeStoredPath(self::get('logo_path'));
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::url($path);
    }

    public static function getFaviconUrl(): ?string
    {
        $path = self::normalizeStoredPath(self::get('favicon_path'));
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::url($path);
    }

    public static function getBaseCurrency(): CurrencyCode
    {
        return CurrencyCode::tryFrom(self::get('base_currency_code', 'NGN'))
            ?? CurrencyCode::NGN;
    }

    public static function getFiscalYearStart(int $year): Carbon
    {
        $month = self::get('fiscal_year_start_month', '01');

        return Carbon::create($year, (int) $month, 1)->startOfDay();
    }

    public static function getCurrentFiscalYear(): array
    {
        $now = now();
        $startMonth = (int) self::get('fiscal_year_start_month', '01');

        $startDate = Carbon::create($now->year, $startMonth, 1);
        if ($startDate->isAfter($now)) {
            $startDate->subYear();
        }

        return [
            'start' => $startDate->copy(),
            'end' => $startDate->copy()->addYear()->subDay(),
        ];
    }

    public function getCountryAttribute(): ?CountryCode
    {
        return CountryCode::tryFrom($this->country_code);
    }

    public function getBaseCurrencyAttribute(): CurrencyCode
    {
        return CurrencyCode::tryFrom($this->base_currency_code) ?? CurrencyCode::NGN;
    }

    public function getReportingCurrencyAttribute(): ?CurrencyCode
    {
        return $this->reporting_currency_code
            ? CurrencyCode::tryFrom($this->reporting_currency_code)
            : null;
    }

    public function getFiscalStartMonthAttribute(): FiscalMonth
    {
        return FiscalMonth::tryFrom($this->fiscal_year_start_month) ?? FiscalMonth::JANUARY;
    }

    public function getLogoUrlAttribute(): ?string
    {
        $path = self::normalizeStoredPath($this->logo_path);
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::url($path);
    }

    public function getFaviconUrlAttribute(): ?string
    {
        $path = self::normalizeStoredPath($this->favicon_path);
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::url($path);
    }

    public function scopeSingleton(Builder $query): Builder
    {
        return $query->where('id', self::SINGLETON_ID);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    private static function resolveCurrentBusinessId(): ?int
    {
        $requestBusinessId = request()?->integer('business_id');
        if ($requestBusinessId) {
            session(['active_business_id' => $requestBusinessId]);

            return $requestBusinessId;
        }

        $sessionBusinessId = session('active_business_id');
        if (is_numeric($sessionBusinessId)) {
            return (int) $sessionBusinessId;
        }

        if (app()->runningInConsole()) {
            return null;
        }

        $fallbackBusinessId = Business::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        if ($fallbackBusinessId) {
            session(['active_business_id' => (int) $fallbackBusinessId]);
        }

        return $fallbackBusinessId ? (int) $fallbackBusinessId : null;
    }

    private static function normalizeStoredPath(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_array($value)) {
            $first = reset($value);

            return is_string($first) ? $first : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (Str::startsWith($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $first = reset($decoded);

                return is_string($first) ? $first : null;
            }
        }

        return $trimmed;
    }
}
