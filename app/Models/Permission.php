<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $guard_name = 'web';

    protected $primaryKey = 'id';

    protected $fillable = ['id', 'name', 'guard_name'];
}
