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
        // Signed-out visitors to customer pages go to the customer sign-in (the admin panel has its own).
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Live site only: email the team about unexpected errors (throttled).
        $exceptions->report(fn (\Throwable $e) => \App\Support\ErrorAlert::report($e));
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
//
// Walks up from the project folder and uses the first match of
//     <ancestor>/mtt-config/<site folder>/.env   or   <ancestor>/mtt-config/.env
// so it works whatever Hostinger's folder layout is.
$projectRoot = dirname(__DIR__);
$site = basename($projectRoot);
$dir = $projectRoot;
while (($parent = dirname($dir)) !== $dir) {
    foreach ([$parent.'/mtt-config/'.$site, $parent.'/mtt-config'] as $envDir) {
        if (is_file($envDir.'/.env')) {
            $app->useEnvironmentPath($envDir);
            // Uploaded images live beside it in <ancestor>/mtt-uploads/<site>, so redeploys never delete them.
            $_SERVER['MTT_UPLOADS_PATH'] = $parent.'/mtt-uploads/'.$site;
            break 2;
        }
    }
    $dir = $parent;
}

return $app;
