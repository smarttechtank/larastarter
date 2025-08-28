<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Role
    |--------------------------------------------------------------------------
    |
    | This option controls the default role that will be used for new users
    | when no role is specified.
    |
    */
    'default_role' => env('DEFAULT_ROLE', 'user'),

    /*
    |--------------------------------------------------------------------------
    | Available Roles
    |--------------------------------------------------------------------------
    |
    | This option controls the roles that are available in the system.
    | The key is the role name and the value is an array with role details.
    | The 'admin' and 'user' roles are required by the system.
    |
    */
    'roles' => [
        'admin' => [
            'name' => 'admin',
            'description' => 'Administrator with full access to all features',
        ],
        'user' => [
            'name' => 'user',
            'description' => 'Regular user with limited access',
        ],
    ],
];
