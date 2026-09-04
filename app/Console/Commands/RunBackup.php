<?php

namespace App\Console\Commands;

use App\Models\JobRun;
use App\Services\BackupService;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'backup:run {type=auto : database|media|auto}';

    protected $description = 'Create database / media backups according to settings';

    public function handle(BackupService $backups): int
    {
        $type = $this->argument('type');
        JobRun::track('backup:run', function () use ($backups, $type) {
            $done = [];
            if ($type === 'database' || ($type === 'auto' && setting_bool('backup.auto_database', true))) {
                $b = $backups->database('scheduled');
                $done[] = 'database '.$b->humanSize();
            }
            if ($type === 'media' || ($type === 'auto' && setting_bool('backup.auto_media', false))) {
                $b = $backups->media('scheduled');
                $done[] = 'media '.$b->humanSize();
            }
            $pruned = $backups->prune(max(1, (int) setting('backup.keep', 7)));
            $msg = 'Backups: '.(implode(', ', $done) ?: 'none').'; pruned '.$pruned;
            $this->info($msg);
            return $msg;
        });
        return self::SUCCESS;
    }
}
