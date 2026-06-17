<?php

return [
    'oauth' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'token_info_endpoint' => 'https://oauth2.googleapis.com/tokeninfo',
        'allowed_issuers' => [
            'accounts.google.com',
            'https://accounts.google.com',
        ],
    ],
];

