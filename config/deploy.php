<?php

return [
    /*
    | Secret for the post-deploy URL  /_deploy/{token}. Opening it runs
    | migrations and refreshes caches straight away, for hosts whose cron
    | can only run hourly. Leave empty to disable the URL completely.
    */
    'token' => env('DEPLOY_TOKEN'),
];
