<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * GET /_deploy/{token}: runs app:post-deploy on demand (migrate, first-time
 * seed and admin, caches). Returns 404 unless DEPLOY_TOKEN is set and matches,
 * so the URL reveals nothing to anyone without the secret.
 */
class DeployController extends Controller
{
    public function __invoke(string $token): Response
    {
        $secret = (string) config('deploy.token');

        abort_if($secret === '' || ! hash_equals($secret, $token), 404);

        // Own buffer: the command makes nested Artisan calls, which would overwrite Artisan::output().
        $output = new BufferedOutput;
        $exit = Artisan::call('app:post-deploy', ['--force' => true], $output);

        // /_deploy/<token>?backup=1 also makes a database backup straight away (to test backups).
        if ($exit === 0 && request()->boolean('backup')) {
            Artisan::call('app:backup-database', [], $output);
        }

        // /_deploy/<token>?import_catalogue=dev copies the dev site's catalogue into this site
        // (replacing this site's). Backs up this site's database first.
        if ($exit === 0 && filled($source = request()->query('import_catalogue'))) {
            $output->writeln("\n— Import catalogue from {$source} —");
            Artisan::call('app:backup-database', [], $output);
            $exit = Artisan::call('app:import-catalogue', ['source' => (string) $source], $output);
        }

        return response(
            ($exit === 0 ? "OK\n\n" : "FAILED (see storage/logs)\n\n").$output->fetch(),
            $exit === 0 ? 200 : 500,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                'X-Robots-Tag' => 'noindex',
                'Cache-Control' => 'no-store',
            ]
        );
    }
}
