<?php

return [

    /*
    | Password given to an account created without one. The user keeps it until they
    | change it on the profile page. MVP: the next step is an invitation email with a
    | set-password link.
    */

    'default_password' => env('ACCOUNT_DEFAULT_PASSWORD', 'password123'),

];
