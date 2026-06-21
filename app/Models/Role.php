<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $guard_name = 'web';

    protected $primaryKey = 'id';

    protected $fillable = ['id', 'name', 'guard_name'];
}
