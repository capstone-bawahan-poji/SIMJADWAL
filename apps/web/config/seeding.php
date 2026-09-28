<?php

return [

    /*
    | Accounts and defaults used by database/seeders. See DATABASE.md §10.1.
    */

    'superadmin' => [
        'email' => env('SUPERADMIN_EMAIL', 'superadmin@penjadwalan.test'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

    'default_password' => env('SEED_DEFAULT_PASSWORD', 'password'),

    'room_capacity' => (int) env('SEED_KAPASITAS_RUANGAN', 40),
    'class_capacity' => (int) env('SEED_KAPASITAS_KELAS', 40),

];
