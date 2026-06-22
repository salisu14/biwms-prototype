<?php

namespace App\Filament\Pages;

use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class BaseViewRecord extends ViewRecord
{
    protected function resolveRecord($key): Model
    {
        if (!is_numeric($key)) {
            abort(404);
        }

        return parent::resolveRecord($key);
    }
}
