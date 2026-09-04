<?php

namespace App\Console\Commands;

use App\Models\JobRun;
use App\Services\AnalyticsService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AnalyticsAggregate extends Command
{
    protected $signature = 'analytics:aggregate {date? : Y-m-d (default today)} {--days=1 : Number of days back to (re)aggregate}';

    protected $description = 'Summarise raw analytics into daily aggregate tables';

    public function handle(AnalyticsService $analytics, NotificationService $notifications): int
    {
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now();
        $days = max(1, (int) $this->option('days'));
        JobRun::track('analytics:aggregate', function () use ($analytics, $date, $days) {
            for ($i = 0; $i < $days; $i++) {
                $d = $date->copy()->subDays($i);
                $analytics->aggregateDay($d);
                $this->info('Aggregated '.$d->toDateString());
            }
            return "Aggregated {$days} day(s) ending ".$date->toDateString();
        });
        $notifications->checkTrafficMilestones();
        $notifications->checkSystemHealth();
        return self::SUCCESS;
    }
}
