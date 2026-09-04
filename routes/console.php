<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled jobs (run `php artisan schedule:work` locally or add the cron:
| * * * * * php /path/artisan schedule:run >> /dev/null 2>&1)
|--------------------------------------------------------------------------
*/

Schedule::command('posts:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('analytics:aggregate')->everyTenMinutes()->withoutOverlapping();
Schedule::command('analytics:aggregate --days=2')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('analytics:retention')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('google:sync all')->everySixHours()->withoutOverlapping();
Schedule::command('links:check')->weeklyOn(1, '04:00')->withoutOverlapping();
Schedule::command('backup:run auto')->dailyAt('02:00')->withoutOverlapping()->when(fn () => in_array(setting('backup.frequency', 'daily'), ['daily'], true));
Schedule::command('backup:run auto')->weeklyOn(0, '02:00')->withoutOverlapping()->when(fn () => setting('backup.frequency', 'daily') === 'weekly');
Schedule::command('queue:work --stop-when-empty --max-time=300')->everyFiveMinutes()->withoutOverlapping();
