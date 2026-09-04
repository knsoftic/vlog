<?php

namespace App\Console\Commands;

use App\Models\AdminLog;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Models\Consent;
use App\Models\JobRun;
use App\Models\PageView;
use App\Models\RateLimitBlock;
use App\Models\RealtimeVisitor;
use App\Models\SearchLog;
use App\Models\SecurityLog;
use App\Models\SystemLog;
use App\Models\VideoEvent;
use App\Models\Visitor;
use Illuminate\Console\Command;

/**
 * Applies the configured retention policy: raw analytics rows are deleted, IPs in security/admin logs
 * are anonymised, old consents/notifications are purged. Aggregates are kept (they hold no personal data).
 */
class AnalyticsRetention extends Command
{
    protected $signature = 'analytics:retention';

    protected $description = 'Delete / anonymise analytics and log data older than the configured retention periods';

    public function handle(): int
    {
        JobRun::track('analytics:retention', function () {
            $days = max(7, (int) setting('analytics.retention_days', 365));
            $secDays = max(7, (int) setting('analytics.security_retention_days', 180));
            $consentDays = max(30, (int) setting('consent.retention_days', 365));
            $cut = now()->subDays($days);
            $out = [];

            $out[] = 'page_views: '.$this->chunkedDelete(PageView::where('viewed_at', '<', $cut));
            $out[] = 'video_events: '.$this->chunkedDelete(VideoEvent::where('created_at', '<', $cut));
            $out[] = 'analytics_events: '.$this->chunkedDelete(AnalyticsEvent::where('created_at', '<', $cut));
            $out[] = 'search_logs: '.$this->chunkedDelete(SearchLog::where('created_at', '<', $cut));
            $out[] = 'sessions: '.$this->chunkedDelete(AnalyticsSession::where('started_at', '<', $cut));
            $out[] = 'visitors: '.$this->chunkedDelete(Visitor::where('last_seen_at', '<', $cut));
            // IP hashes on sessions are only needed for short-term abuse investigation (7 days)
            $out[] = 'ip_hash cleared: '.AnalyticsSession::where('started_at', '<', now()->subDays(7))->whereNotNull('ip_hash')->update(['ip_hash' => null]);

            // Security / admin logs: anonymise IP after the security retention window, delete after 2x
            $secCut = now()->subDays($secDays);
            $out[] = 'security ip anonymised: '.SecurityLog::where('created_at', '<', $secCut)->whereNotNull('ip')->update(['ip' => null, 'user_agent' => null]);
            $out[] = 'admin ip anonymised: '.AdminLog::where('created_at', '<', $secCut)->whereNotNull('ip')->update(['ip' => null, 'user_agent' => null]);
            $out[] = 'security deleted: '.$this->chunkedDelete(SecurityLog::where('created_at', '<', now()->subDays($secDays * 2)));
            $out[] = 'system logs deleted: '.$this->chunkedDelete(SystemLog::where('created_at', '<', now()->subDays($secDays)));
            $out[] = 'consents deleted: '.$this->chunkedDelete(Consent::where('updated_at', '<', now()->subDays($consentDays)));
            $out[] = 'realtime pruned: '.RealtimeVisitor::where('last_seen_at', '<', now()->subMinutes(10))->delete();
            $out[] = 'rate blocks pruned: '.RateLimitBlock::where('blocked_until', '<', now())->delete();
            $out[] = 'job runs pruned: '.JobRun::where('started_at', '<', now()->subDays(30))->delete();
            $msg = implode(', ', $out);
            $this->info($msg);
            return $msg;
        });
        return self::SUCCESS;
    }

    protected function chunkedDelete($query): int
    {
        $total = 0;
        do {
            $n = (clone $query)->limit(5000)->delete();
            $total += $n;
        } while ($n > 0);
        return $total;
    }
}
