<?php

namespace App\Console\Commands;

use Database\Seeders\SampleCatalogueSeeder;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Copies the product catalogue from another site on the same hosting account
 * (by default the dev site) into this site's database, replacing this site's
 * catalogue. Used once at launch: the team enters products on dev, then
 *     https://<live>/_deploy/<token>?import_catalogue=dev
 * copies them to live.
 *
 * The source database details are read from that site's own .env
 * (mtt-config/<source>/.env), so nothing has to be typed anywhere.
 * Copied: places, suppliers, vehicles, transfers, tours, tickets, add-ons,
 * blackout dates, pricing rules, promo codes, currencies, and uploaded images.
 * Never touched: users, bookings, carts, settings, website pages, email templates.
 */
class ImportCatalogue extends Command
{
    protected $signature = 'app:import-catalogue
        {source=dev : Site folder to copy from (its .env is in mtt-config/<source>/.env)}
        {--force : Run even if this site already has bookings}';

    protected $description = 'Replace this site\'s catalogue with the catalogue of another site on the same server (e.g. dev)';

    /** Parents before children, so the order also documents the dependencies. */
    private const TABLES = [
        'countries', 'cities', 'locations',
        'suppliers', 'vehicle_types',
        'transfer_routes', 'transfer_route_prices',
        'tours', 'tour_sic_prices', 'tour_private_prices',
        'attractions', 'attraction_ticket_types',
        'extras', 'blackout_dates', 'pricing_rules', 'coupons',
        'currencies',
    ];

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $site = basename(base_path());

        if ($source === $site) {
            $this->error("This site is \"{$site}\"; it cannot import from itself.");

            return self::FAILURE;
        }

        if (! $this->option('force') && Schema::hasTable('bookings') && DB::table('bookings')->exists()) {
            $this->error('This site already has bookings. The import replaces the whole catalogue, so it is blocked. Run with --force only if you are sure.');

            return self::FAILURE;
        }

        $envFile = $this->findEnv($source);
        if ($envFile === null) {
            $this->error("Could not find mtt-config/{$source}/.env next to this site.");

            return self::FAILURE;
        }

        $env = Dotenv::parse((string) file_get_contents($envFile));
        $default = config('database.default');
        $base = config("database.connections.{$default}");

        config(['database.connections.catalogue_source' => array_merge($base, [
            'host' => $env['DB_HOST'] ?? $base['host'],
            'port' => $env['DB_PORT'] ?? $base['port'],
            'database' => $env['DB_DATABASE'] ?? '',
            'username' => $env['DB_USERNAME'] ?? '',
            'password' => $env['DB_PASSWORD'] ?? '',
        ])]);

        if (($env['DB_DATABASE'] ?? '') === '' || $env['DB_DATABASE'] === config("database.connections.{$default}.database")) {
            $this->error("The {$source} site's database is missing or is this site's own database.");

            return self::FAILURE;
        }

        $from = DB::connection('catalogue_source');
        $from->getPdo(); // fail early with a clear message if the login is wrong
        $this->info("Copying catalogue from {$source} ({$env['DB_DATABASE']})");

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($from) {
                foreach (array_reverse(self::TABLES) as $table) {
                    DB::table($table)->delete();
                }

                foreach (self::TABLES as $table) {
                    if (! $from->getSchemaBuilder()->hasTable($table)) {
                        $this->warn("  {$table}: not in source, skipped");

                        continue;
                    }

                    // Only columns both databases have, in case one is a migration behind.
                    $columns = array_values(array_intersect(
                        Schema::getColumnListing($table),
                        $from->getSchemaBuilder()->getColumnListing($table),
                    ));

                    $count = 0;
                    $from->table($table)->select($columns)->orderBy('id')->chunk(500, function ($rows) use ($table, &$count) {
                        DB::table($table)->insert($rows->map(fn ($r) => (array) $r)->all());
                        $count += $rows->count();
                    });

                    $this->line(sprintf('  %-26s %d', $table, $count));
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->copyImages($source);

        $hidden = SampleCatalogueSeeder::deactivate();
        if ($hidden > 0) {
            $this->line("Turned off {$hidden} sample product(s).");
        }

        Cache::flush();
        $this->info('Catalogue imported.');

        return self::SUCCESS;
    }

    /** Same lookup as bootstrap/app.php: walk up from this site to find mtt-config/<source>/.env. */
    private function findEnv(string $source): ?string
    {
        $dir = base_path();
        while (($parent = dirname($dir)) !== $dir) {
            if (is_file($file = $parent.'/mtt-config/'.$source.'/.env')) {
                return $file;
            }
            $dir = $parent;
        }

        return null;
    }

    /** Uploaded images live in mtt-uploads/<site>; copy the source site's into ours (existing files kept). */
    private function copyImages(string $source): void
    {
        $target = (string) config('filesystems.disks.uploads.root');
        $from = dirname($target).'/'.$source;

        if ($target === '' || ! is_dir($from)) {
            $this->line('No images to copy.');

            return;
        }

        $copied = 0;
        foreach (File::allFiles($from) as $file) {
            $dest = $target.'/'.$file->getRelativePathname();
            if (! is_file($dest)) {
                File::ensureDirectoryExists(dirname($dest));
                File::copy($file->getPathname(), $dest);
                $copied++;
            }
        }

        $this->line("Copied {$copied} image(s).");
    }
}
