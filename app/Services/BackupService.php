<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Database (pure-PHP SQL dump) and media (zip) backups stored on the private "local" disk.
 */
class BackupService
{
    public function database(string $trigger = 'manual', ?int $userId = null): Backup
    {
        $filename = 'db-'.now()->format('Y-m-d_His').'.sql.gz';
        $rel = 'backups/'.$filename;
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');
        $full = $disk->path($rel);
        $backup = Backup::create(['type' => 'database', 'filename' => $filename, 'disk' => 'local', 'path' => $rel, 'status' => 'running', 'trigger' => $trigger, 'created_by' => $userId]);
        try {
            $gz = gzopen($full, 'wb6');
            gzwrite($gz, "-- ".config('app.name')." database backup\n-- Generated: ".now()->toDateTimeString()."\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");
            $sqlite = DB::getDriverName() === 'sqlite';
            $tables = $sqlite
                ? array_map(fn ($r) => $r->name, DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                : array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW TABLES'));
            foreach ($tables as $table) {
                if ($sqlite) {
                    $createSql = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$table])->sql;
                } else {
                    $create = DB::select("SHOW CREATE TABLE `{$table}`")[0];
                    $createSql = array_values((array) $create)[1];
                }
                gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n");
                DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use ($gz, $table) {
                    $values = [];
                    foreach ($rows as $row) {
                        $vals = [];
                        foreach ((array) $row as $v) {
                            $vals[] = $v === null ? 'NULL' : DB::getPdo()->quote((string) $v);
                        }
                        $values[] = '('.implode(',', $vals).')';
                    }
                    if ($values) {
                        $cols = implode('`,`', array_keys((array) $rows->first()));
                        gzwrite($gz, "INSERT INTO `{$table}` (`{$cols}`) VALUES\n".implode(",\n", $values).";\n");
                    }
                });
                gzwrite($gz, "\n");
            }
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
            gzclose($gz);
            $backup->update(['status' => 'completed', 'size' => filesize($full)]);
        } catch (\Throwable $e) {
            $backup->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
        return $backup;
    }

    public function media(string $trigger = 'manual', ?int $userId = null): Backup
    {
        $filename = 'media-'.now()->format('Y-m-d_His').'.zip';
        $rel = 'backups/'.$filename;
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');
        $full = $disk->path($rel);
        $backup = Backup::create(['type' => 'media', 'filename' => $filename, 'disk' => 'local', 'path' => $rel, 'status' => 'running', 'trigger' => $trigger, 'created_by' => $userId]);
        try {
            $zip = new ZipArchive;
            if ($zip->open($full, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not create zip archive.');
            }
            $public = Storage::disk('public');
            foreach ($public->allFiles() as $f) {
                if (str_starts_with($f, 'backups/')) {
                    continue;
                }
                $zip->addFile($public->path($f), $f);
            }
            $zip->close();
            $backup->update(['status' => 'completed', 'size' => filesize($full)]);
        } catch (\Throwable $e) {
            $backup->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
        return $backup;
    }

    /** Restore a database backup (destructive: replaces current tables). */
    public function restoreDatabase(Backup $backup): void
    {
        if ($backup->type !== 'database') {
            throw new \InvalidArgumentException('Not a database backup.');
        }
        $full = Storage::disk($backup->disk)->path($backup->path);
        if (! is_file($full)) {
            throw new \RuntimeException('Backup file is missing.');
        }
        $sql = gzdecode(file_get_contents($full));
        if ($sql === false) {
            throw new \RuntimeException('Backup file could not be decompressed.');
        }
        DB::unprepared($sql);
    }

    public function restoreMedia(Backup $backup): void
    {
        if ($backup->type !== 'media') {
            throw new \InvalidArgumentException('Not a media backup.');
        }
        $full = Storage::disk($backup->disk)->path($backup->path);
        $zip = new ZipArchive;
        if ($zip->open($full) !== true) {
            throw new \RuntimeException('Could not open backup archive.');
        }
        $zip->extractTo(Storage::disk('public')->path(''));
        $zip->close();
    }

    public function prune(int $keep): int
    {
        $deleted = 0;
        foreach (['database', 'media'] as $type) {
            $old = Backup::where('type', $type)->where('status', 'completed')->orderByDesc('created_at')->skip($keep)->take(100)->get();
            foreach ($old as $b) {
                Storage::disk($b->disk)->delete($b->path);
                $b->delete();
                $deleted++;
            }
        }
        return $deleted;
    }
}
