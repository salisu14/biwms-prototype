<?php

namespace App\Policies;

class MaintenanceContractAssetPolicy extends BaseFilamentPolicy
{
    protected string $module = 'maintenance_contract_assets';

    protected string $resource = 'maintenance_contract_asset';
}
