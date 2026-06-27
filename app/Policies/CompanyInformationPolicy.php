<?php

namespace App\Policies;

class CompanyInformationPolicy extends BaseFilamentPolicy
{
    protected string $module = 'company_information';

    protected string $resource = 'company_information';
}
