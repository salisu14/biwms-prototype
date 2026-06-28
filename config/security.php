<?php

return [
    'two_factor_required_roles' => [
        'super_admin',
        'admin',
        'finance-manager',
        'hr-manager',
    ],

    'admin_idle_timeout_minutes' => (int) env('ADMIN_IDLE_TIMEOUT_MINUTES', 30),

    'admin_absolute_session_lifetime_minutes' => (int) env('ADMIN_ABSOLUTE_SESSION_LIFETIME_MINUTES', 480),
];
