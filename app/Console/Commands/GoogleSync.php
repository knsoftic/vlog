<?php

namespace App\Console\Commands;

use App\Models\JobRun;
use App\Services\AdSenseService;
use App\Services\SearchConsoleService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GoogleSync extends Command
{
    protected $signature = 'google:sync {service=all : adsense|search_console|all} {--from=} {--to=}';

    protected $description = 'Sync AdSense and Search Console reports through the authorised Google APIs';

    public function handle(AdSenseService $adsense, SearchConsoleService $gsc): int
    {
        $service = $this->argument('service');
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to')) : null;
        $ok = true;

        if (in_array($service, ['adsense', 'all'], true)) {
            if ($adsense->isConnected() && (setting_bool('adsense.auto_sync', true) || $service === 'adsense')) {
                try {
                    JobRun::track('adsense:sync', function () use ($adsense, $from, $to) {
                        $c = $adsense->sync($from, $to);
                        return 'AdSense rows: '.json_encode($c);
                    });
                    $this->info('AdSense synced.');
                } catch (\Throwable $e) {
                    $this->error('AdSense: '.$e->getMessage());
                    $ok = false;
                }
            } else {
                $this->line('AdSense not connected or auto-sync disabled; skipped.');
            }
        }
        if (in_array($service, ['search_console', 'all'], true)) {
            if ($gsc->isConnected() && $gsc->siteUrl() && (setting_bool('gsc.auto_sync', true) || $service === 'search_console')) {
                try {
                    JobRun::track('search_console:sync', function () use ($gsc, $from, $to) {
                        $c = $gsc->sync($from, $to);
                        return 'GSC rows: '.json_encode($c);
                    });
                    $this->info('Search Console synced.');
                } catch (\Throwable $e) {
                    $this->error('Search Console: '.$e->getMessage());
                    $ok = false;
                }
            } else {
                $this->line('Search Console not connected or auto-sync disabled; skipped.');
            }
        }
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
