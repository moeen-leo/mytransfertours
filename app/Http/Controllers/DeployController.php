<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * GET /_deploy/{token}: runs app:post-deploy on demand (migrate, first-time
 * seed, caches). Returns 404 unless DEPLOY_TOKEN is set and matches, so the
 * URL reveals nothing to anyone without the secret.
 */
class DeployController extends Controller
{
    public function __invoke(string $token): Response
    {
        $secret = (string) config('deploy.token');

        abort_if($secret === '' || ! hash_equals($secret, $token), 404);

        $exit = Artisan::call('app:post-deploy', ['--force' => true]);

        return response(
            ($exit === 0 ? "OK\n\n" : "FAILED (see storage/logs)\n\n").Artisan::output(),
            $exit === 0 ? 200 : 500,
            ['Content-Type' => 'text/plain; charset=utf-8', 'X-Robots-Tag' => 'noindex']
        );
    }
}
