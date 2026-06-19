<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // database/migrations/2026_05_27_000001_create_company_information_table.php
        Schema::create('company_information', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('trading_name')->nullable();
            $table->string('registration_no')->nullable();
            $table->string('tax_registration_no')->nullable();
            $table->string('tax_office')->nullable();

            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state_province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country_code', 3)->default('NGA');

            // Contact
            $table->string('phone_no')->nullable();
            $table->string('mobile_no')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Optional Contact Person
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_title')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->string('contact_person_email')->nullable();

            // Logo & Branding
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();

            // Banking
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('swift_code')->nullable();

            // Fiscal Settings
            $table->string('fiscal_year_start_month')->default('01');
            $table->string('base_currency_code', 3)->default('NGN');
            $table->string('reporting_currency_code', 3)->nullable();

            // Document Defaults
            $table->text('terms_conditions')->nullable();
            $table->text('invoice_footer')->nullable();

            $table->timestamps();
        });

        // Seed singleton record
        DB::table('company_information')->insert([
            'company_name' => 'BIFLI Group',
            'country_code' => 'NGA',
            'base_currency_code' => 'NGN',
            'fiscal_year_start_month' => '01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_information');
    }
};
