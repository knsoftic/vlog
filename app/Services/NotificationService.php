<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\AnalyticsDaily;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Cache;

/**
 * Admin notifications: milestones, sync failures, sitemap issues, security warnings.
 * Milestones are purely informational and never used to generate traffic.
 */
class NotificationService
{
    public function checkTrafficMilestones(): void
    {
        $milestones = array_filter(array_map('intval', explode(',', (string) setting('notify.traffic_milestones', '1000,10000,100000,1000000'))));
        if (! $milestones) {
            return;
        }
        $total = (int) AnalyticsDaily::sum('page_views');
        $reached = (int) Cache::get('milestone.reached', 0);
        foreach ($milestones as $m) {
            if ($total >= $m && $m > $reached) {
                AdminNotification::announce('milestone', 'Traffic milestone reached: '.number_format($m).' page views', 'Your site has now recorded '.number_format($total).' human page views in total.', 'success', route('admin.analytics.overview'));
                $reached = $m;
            }
        }
        Cache::forever('milestone.reached', $reached);
    }

    public function checkSystemHealth(): void
    {
        $recent500 = SystemLog::whereIn('type', ['500', 'exception'])->where('created_at', '>=', now()->subHour())->sum('occurrences');
        if ($recent500 >= 10) {
            AdminNotification::announce('broken_page', 'Elevated server errors', "{$recent500} server errors were logged in the last hour.", 'critical', route('admin.logs.system'));
        }
        $recent404 = SystemLog::where('type', '404')->where('created_at', '>=', now()->subDay())->sum('occurrences');
        if ($recent404 >= 100) {
            AdminNotification::announce('broken_page', 'High number of 404 errors', "{$recent404} not-found requests in the last 24 hours. Review broken links and add redirects.", 'warning', route('admin.seo.broken-links'));
        }
        $failedLogins = \App\Models\SecurityLog::where('type', 'failed_login')->where('created_at', '>=', now()->subHour())->count();
        if ($failedLogins >= 20) {
            AdminNotification::announce('security', 'Unusual login activity', "{$failedLogins} failed admin login attempts in the last hour.", 'critical', route('admin.logs.security'));
        }
    }

    public function unreadCount(): int
    {
        return AdminNotification::where('is_read', false)->count();
    }
}
