<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily database backup without SSH or mysqldump: writes a gzipped .sql file next to
 * the uploads folder (outside the web folder, e.g. domains/<site>/mtt-backups/live)
 * and keeps the last 14. Download them from Hostinger's File Manager if ever needed.
 */
class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database {--keep=14 : How many backups to keep}';

    protected $description = 'Save a gzipped SQL backup of the database outside the web folder';

    public static function folder(): string
    {
        $uploads = $_SERVER['MTT_UPLOADS_PATH'] ?? null;

        return $uploads ? str_replace('/mtt-uploads/', '/mtt-backups/', $uploads) : storage_path('app/backups');
    }

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            $this->line('Backups are only made for MySQL/MariaDB.');

            return self::SUCCESS;
        }

        $dir = self::folder();
        if (! is_dir($dir) && ! @mkdir($dir, 0750, true)) {
            $this->error("Cannot create {$dir}");

            return self::FAILURE;
        }

        $file = $dir.'/'.DB::getDatabaseName().'-'.now('Asia/Kuala_Lumpur')->format('Y-m-d-His').'.sql.gz';
        $gz = gzopen($file.'.part', 'wb6');
        $pdo = DB::connection()->getPdo();

        gzwrite($gz, "-- MyTransferTours backup ".now()->toIso8601String()."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
            $table = array_values((array) $row)[0];
            if (in_array($table, ['cache', 'cache_locks', 'sessions', 'jobs'], true)) {
                continue;   // temporary data, recreated automatically
            }
            $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `{$table}`"))[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n");

            $stmt = $pdo->query("SELECT * FROM `{$table}`", \PDO::FETCH_NUM);
            $batch = [];
            while (($r = $stmt->fetch()) !== false) {
                $batch[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)).')';
                if (count($batch) === 200) {
                    gzwrite($gz, "INSERT INTO `{$table}` VALUES ".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($gz, "INSERT INTO `{$table}` VALUES ".implode(",\n", $batch).";\n");
            }
            gzwrite($gz, "\n");
        }

        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        rename($file.'.part', $file);

        $files = glob($dir.'/*.sql.gz') ?: [];
        rsort($files);
        foreach (array_slice($files, max(1, (int) $this->option('keep'))) as $old) {
            @unlink($old);
        }

        $this->info('Backup saved: '.$file.' ('.round(filesize($file) / 1024).' KB)');

        return self::SUCCESS;
    }
}
