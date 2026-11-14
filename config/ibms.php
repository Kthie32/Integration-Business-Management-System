<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial Super Administrator
    |--------------------------------------------------------------------------
    |
    | Used once by the SuperAdminSeeder to create the first account. If no password is
    | provided, a random one is generated and printed to the console a single time.
    | Remove the password from the environment after the first login.
    |
    */

    'super_admin' => [
        'name' => env('IBMS_SUPER_ADMIN_NAME', 'System Owner'),
        'email' => env('IBMS_SUPER_ADMIN_EMAIL'),
        'password' => env('IBMS_SUPER_ADMIN_PASSWORD'),
    ],
];
