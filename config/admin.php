<?php

return [
    /*
    | Address of the admin panel, e.g. ADMIN_PATH=ops-7k2q gives
    | https://<site>/ops-7k2q. Use something hard to guess; it is still
    | password-protected and limited to admin/staff accounts.
    */
    'path' => trim((string) (env('ADMIN_PATH') ?: 'admin'), '/') ?: 'admin',

    /*
    | First admin account. If no admin exists yet, the post-deploy step
    | creates one for this email with a random password and prints the
    | password once. Change it after the first login (top-right > Profile).
    */
    'email' => env('ADMIN_EMAIL'),

    /*
    | Optional: limit "Sign in with Google" on the admin login to one company
    | domain, e.g. ADMIN_GOOGLE_DOMAIN=mytransfertours.com. Empty = any Google
    | account, as long as its email belongs to an Admin or Staff user.
    */
    'google_domain' => trim((string) env('ADMIN_GOOGLE_DOMAIN', ''), '@ '),
];
