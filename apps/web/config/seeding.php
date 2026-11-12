<?php

return [

    /*
    | Accounts and defaults used by database/seeders. See DATABASE.md §10.1.
    */

    'superadmin' => [
        'email' => env('SUPERADMIN_EMAIL', 'superadmin@example.test'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

    /*
    | Demo data (accounts, programs, schedules). Unset = on outside production, off in production.
    | A demo server in production sets SEED_DEMO=true together with a strong SEED_DEFAULT_PASSWORD.
    */
    'demo' => env('SEED_DEMO'),

    /*
    | Programs loaded from database/seeders/data/master. Comma separated slugs, or "all".
    | Informatika is the full-detail program; the rest keep the demo small. The TPB seeder needs
    | Matematika, Fisika, Statistika, Teknik Kimia, Informatika and Bisnis Digital lecturers.
    */
    'demo_programs' => env('SEED_DEMO_PROGRAMS', 'informatika,sistem-informasi,matematika,fisika,statistika,bisnis-digital,teknik-kimia,teknik-lingkungan'),

    'default_password' => env('SEED_DEFAULT_PASSWORD', 'password'),

    'room_capacity' => (int) env('SEED_KAPASITAS_RUANGAN', 50),
    'class_capacity' => (int) env('SEED_KAPASITAS_KELAS', 50),

];
