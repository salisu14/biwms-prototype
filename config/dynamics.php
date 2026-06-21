<?php

return [
    'connections' => [
        'business_central' => [
            'base_url' => env('BC_BASE_URL'), // https://api.businesscentral.dynamics.com/v2.0/{tenant}/{environment}
            'version' => 'ODataV4',
            'auth' => 'oauth',
            'oauth' => [
                'client_id' => env('BC_CLIENT_ID'),
                'client_secret' => env('BC_CLIENT_SECRET'),
                'token_url' => env('BC_TOKEN_URL'),
                'scope' => 'https://api.businesscentral.dynamics.com/.default'
            ],
            'company_id' => env('BC_COMPANY_ID'),
        ]
    ]
];
