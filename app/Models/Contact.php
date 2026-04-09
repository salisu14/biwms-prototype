<?php

namespace App\Models;

use App\Enums\ContactRole;
use App\Enums\ContactType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'full_name',
        'company_name',

        'type',
        'role',

        'email',
        'phone',
        'mobile',

        'address',
        'address_2',
        'city',
        'state',
        'county',
        'postal_code',
        'post_code',
        'country',
        'country_region_code',

        'tax_id',
        'vat_registration_no',

        'currency',
        'currency_code',

        'general_business_posting_group_id',
        'vendor_posting_group_id',
        'vat_bus_posting_group',
    ];

    protected $casts = [
        'type' => ContactType::class,
        'role' => ContactRole::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships (Extension Pattern)
    |--------------------------------------------------------------------------
    */

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function vendor(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers (VERY useful in business logic)
    |--------------------------------------------------------------------------
    */

    public function isCustomer(): bool
    {
        return in_array($this->role, [
            ContactRole::CUSTOMER,
            ContactRole::BOTH,
        ]);
    }

    public function isVendor(): bool
    {
        return in_array($this->role, [
            ContactRole::VENDOR,
            ContactRole::BOTH,
        ]);
    }

    public function isCompany(): bool
    {
        return $this->type === ContactType::COMPANY;
    }

    public function isPerson(): bool
    {
        return $this->type === ContactType::PERSON;
    }
}
