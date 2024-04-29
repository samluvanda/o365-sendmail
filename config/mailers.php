<?php

return [
    'o365-sendmail' => [
        'transport' => 'o365-sendmail',
        'tenant' => env('O365SENDMAIL_TENANT', ''),
        'client_id' => env('O365SENDMAIL_CLIENT_ID', ''),
        'client_secret' => env('O365SENDMAIL_CLIENT_SECRET', ''),
    ],
];
