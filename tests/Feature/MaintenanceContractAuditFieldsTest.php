<?php

use App\Models\MaintenanceContract;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('it assigns audit fields during maintenance contract model events', function () {
    $user = new User;
    $user->id = 123;

    Auth::setUser($user);

    $contract = new class extends MaintenanceContract
    {
        public function triggerCreating(): mixed
        {
            return $this->fireModelEvent('creating');
        }

        public function triggerUpdating(): mixed
        {
            $this->exists = true;

            return $this->fireModelEvent('updating');
        }
    };

    $contract->triggerCreating();

    expect($contract->created_by)->toBe(123)
        ->and($contract->modified_by)->toBe(123);

    $contract->modified_by = null;
    $contract->triggerUpdating();

    expect($contract->modified_by)->toBe(123);
});
