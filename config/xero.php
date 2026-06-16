<?php

return [
    'client_id' => env('XERO_CLIENT_ID'),
    'client_secret' => env('XERO_CLIENT_SECRET'),
    'redirect_uri'=> env('XERO_REDIRECT_URI'),
    'scopes' => env(
        'XERO_SCOPES',
        'openid email profile offline_access accounting.settings accounting.transactions accounting.contacts'
    ),
    'url_authorize' => 'https://login.xero.com/identity/connect/authorize',
    'url_access_token' => 'https://identity.xero.com/connect/token',
    'url_resource_owner' => 'https://api.xero.com/api.xro/2.0/Organisation',
];
