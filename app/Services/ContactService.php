<?php

namespace App\Services;

use App\Enums\ContactRole;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class ContactService
{
    /**
     * @throws \Throwable
     */
    public function convertToCustomer(Contact $contact, array $data): Customer
    {
        return DB::transaction(function () use ($contact, $data) {
            // 1. Generate Customer Number
            $customerNumber = app(NumberSeriesService::class)->getNextNoFromSeries(['CUSTOMER'], null, 'Customer');

            // 2. Create Customer
            $customer = Customer::create([
                'customer_number' => $customerNumber,
                'name' => $contact->name,
                'address' => $contact->address,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'customer_type' => $data['customer_type'],
                'customer_posting_group_id' => $data['customer_posting_group_id'],
                'general_business_posting_group_id' => $data['general_business_posting_group_id'] ?? $contact->general_business_posting_group_id,
                'contact_id' => $contact->id,
            ]);

            // 3. Update Contact Role
            $newRole = $contact->role === ContactRole::VENDOR ? ContactRole::BOTH : ContactRole::CUSTOMER;
            $contact->update(['role' => $newRole]);

            return $customer;
        });
    }

    /**
     * @throws \Throwable
     */
    public function convertToVendor(Contact $contact, array $data): Vendor
    {
        return DB::transaction(function () use ($contact, $data) {
            // 1. Generate Vendor Code
            $vendorCode = app(NumberSeriesService::class)->getNextNoFromSeries(['VENDOR'], null, 'Vendor');

            // 2. Create Vendor
            $vendor = Vendor::create([
                'vendor_code' => $vendorCode,
                'vendor_name' => $contact->name,
                'address' => $contact->address,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'mobile' => $contact->mobile,
                'vendor_posting_group_id' => $data['vendor_posting_group_id'],
                'general_business_posting_group_id' => $data['general_business_posting_group_id'] ?? $contact->general_business_posting_group_id,
                'contact_id' => $contact->id,
            ]);

            // 3. Update Contact Role
            $newRole = $contact->role === ContactRole::CUSTOMER ? ContactRole::BOTH : ContactRole::VENDOR;
            $contact->update(['role' => $newRole]);

            return $vendor;
        });
    }
}
