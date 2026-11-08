<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Runs once after each Git deploy, triggered by the every-minute scheduler.
 * The GitHub build writes the commit id to BUILD_ID; when it differs from the
 * last one we handled, migrate the database and rebuild caches. Lets Hostinger
 * deploy without anyone running commands over SSH.
 */
class PostDeploy extends Command
{
    protected $signature = 'app:post-deploy {--force : Run even if this build was already handled}';

    protected $description = 'Migrate and refresh caches when a new build has been deployed';

    public function handle(): int
    {
        $buildFile = base_path('BUILD_ID');
        $doneFile = storage_path('app/deployed_build');

        if (! is_file($buildFile)) {
            return self::SUCCESS; // local development: nothing to do
        }

        $build = trim((string) file_get_contents($buildFile));
        $done = is_file($doneFile) ? trim((string) file_get_contents($doneFile)) : null;

        if ($build === $done && ! $this->option('force')) {
            return self::SUCCESS;
        }

        $this->info("Post-deploy for build {$build}");

        try {
            // First deploy: create the encryption key inside the external .env (no SSH needed).
            if (blank(config('app.key'))) {
                Artisan::call('key:generate', ['--force' => true]);
                $this->info('Generated APP_KEY');
            }

            Artisan::call('migrate', ['--force' => true]);
            $this->line(Artisan::output());

            // First deploy only: load Malaysia starter data (cities, locations, vehicles).
            if (\App\Models\Country::query()->doesntExist()) {
                Artisan::call('db:seed', ['--class' => 'MalaysiaStarterSeeder', '--force' => true]);
            }

            Artisan::call('optimize:clear');
            Artisan::call('optimize');
        } catch (\Throwable $e) {
            // Leave deployed_build unchanged so the next minute retries; log loudly.
            Log::error('Post-deploy failed', ['build' => $build, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        file_put_contents($doneFile, $build);
        Log::info('Post-deploy finished', ['build' => $build]);

        return self::SUCCESS;
    }
}
