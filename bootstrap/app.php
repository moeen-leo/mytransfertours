<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Stripe posts webhooks without a CSRF token; the controller verifies the Stripe signature instead.
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
        // Hostinger sits behind a proxy; trust it so HTTPS URLs are generated correctly.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();

/*
| Keep the .env file OUTSIDE the web folder and outside the deployed folder:
| Git deploys replace the deployed folder, and anything under public_html can
| be reached from a browser.
|
| On Hostinger, if the app is deployed to
|     ~/domains/mytransfertours.com/public_html/dev
| its .env lives at
|     ~/domains/mytransfertours.com/mtt-config/dev/.env
| (one folder per site: dev, live, ...). Locally it falls back to the project root.
*/
$projectRoot = dirname(__DIR__);
$dir = $projectRoot;
while (($parent = dirname($dir)) !== $dir) {
    if (basename($parent) === 'public_html') {
        $envDir = dirname($parent).DIRECTORY_SEPARATOR.'mtt-config'.DIRECTORY_SEPARATOR.basename($projectRoot);
        if (is_file($envDir.DIRECTORY_SEPARATOR.'.env')) {
            $app->useEnvironmentPath($envDir);
        }
        break;
    }
    $dir = $parent;
}

return $app;
