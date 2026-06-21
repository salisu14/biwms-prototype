<?php

use App\Enums\ContactRole;
use App\Enums\ContactType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            // ✅ Primary key
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Core Identity
            |--------------------------------------------------------------------------
            */
            $table->string('name'); // short display name
            $table->string('full_name')->nullable();
            $table->string('company_name')->nullable();

            $table->string('type', 20)
                ->default(ContactType::PERSON->value); // person/company

            $table->string('role', 20)
                ->default(ContactRole::CUSTOMER->value); // customer/vendor/both/prospect

            /*
            |--------------------------------------------------------------------------
            | Communication
            |--------------------------------------------------------------------------
            */
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Address
            |--------------------------------------------------------------------------
            */
            $table->string('address')->nullable();
            $table->string('address_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('county')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('post_code')->nullable();
            $table->string('country')->nullable();
            $table->string('country_region_code')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Tax / Compliance
            |--------------------------------------------------------------------------
            */
            $table->string('tax_id')->nullable();
            $table->string('vat_registration_no')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Currency & Payments
            |--------------------------------------------------------------------------
            */
            $table->string('currency')->nullable();
            $table->string('currency_code')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('payment_terms_code')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Posting Groups (ERP / Accounting)
            |--------------------------------------------------------------------------
            */
            $table->foreignId('general_business_posting_group_id')
                ->nullable()
                ->constrained('general_business_posting_groups')
                ->nullOnDelete();

            $table->foreignId('vendor_posting_group_id')
                ->nullable()
                ->constrained('vendor_posting_groups')
                ->nullOnDelete();

            $table->string('vat_bus_posting_group')->nullable();

            /*
            |--------------------------------------------------------------------------
            | System
            |--------------------------------------------------------------------------
            */
            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Useful Indexes
            |--------------------------------------------------------------------------
            */
            $table->index(['type']);
            $table->index(['role']);
            $table->index(['company_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {

            $table->dropForeign(['general_business_posting_group_id']);
            $table->dropForeign(['vendor_posting_group_id']);

            $table->dropColumn([
                'type',
                'role',
                'full_name',
                'company_name',
                'county',
                'post_code',
                'country_region_code',
                'vat_registration_no',
                'currency_code',
                'general_business_posting_group_id',
                'vendor_posting_group_id',
                'vat_bus_posting_group',
            ]);
        });
    }
};
