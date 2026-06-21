<?php

namespace App\Services\Sales;

use App\Models\GeneralPostingSetup;
use App\Models\SalesOrderLine;

class SalesOrderLinePostingService
{
    public function getPostingSetup(SalesOrderLine $line): ?GeneralPostingSetup
    {
        return GeneralPostingSetup::where([
            'general_business_posting_group_id' => $line->salesOrder->general_business_posting_group_id,
            'general_product_posting_group_id' => $line->general_product_posting_group_id,
        ])->first();
    }

    public function getSalesAccount(SalesOrderLine $line)
    {
        return $this->getPostingSetup($line)?->getSalesAccount();
    }

    public function getCogsAccount(SalesOrderLine $line)
    {
        return $this->getPostingSetup($line)?->getCogsAccount();
    }

    public function validate(SalesOrderLine $line): array
    {
        $errors = [];

        if (! $this->getPostingSetup($line)) {
            $errors[] = 'Missing posting setup';
        }

        if (! $this->getSalesAccount($line)) {
            $errors[] = 'Missing sales account';
        }

        if (! $this->getCogsAccount($line)) {
            $errors[] = 'Missing COGS account';
        }

        return $errors;
    }
}
