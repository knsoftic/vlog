<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\AdsenseReport;
use App\Models\GoogleToken;
use App\Models\SystemLog;
use Carbon\Carbon;
use Google\Service\Adsense;

/**
 * AdSense Management API v2 reporting sync.
 * All numbers shown in the dashboard come from this authorised API data; nothing is estimated locally.
 */
class AdSenseService
{
    public function __construct(protected GoogleOAuthService $oauth)
    {
    }

    public function token(): ?GoogleToken
    {
        return GoogleToken::where('service', 'adsense')->first();
    }

    public function isConnected(): bool
    {
        return (bool) $this->token()?->isConnected();
    }

    /** Resolve and cache the AdSense account name (accounts/pub-xxx). */
    public function accountName(): ?string
    {
        $token = $this->token();
        if (! $token) {
            return null;
        }
        if ($token->account_id) {
            return $token->account_id;
        }
        $client = $this->oauth->authorizedClient('adsense');
        if (! $client) {
            return null;
        }
        $svc = new Adsense($client);
        $list = $svc->accounts->listAccounts();
        $accounts = $list->getAccounts() ?: [];
        if (! $accounts) {
            throw new \RuntimeException('No AdSense account is associated with the connected Google account.');
        }
        $acc = $accounts[0];
        $token->update(['account_id' => $acc->getName(), 'account_label' => $acc->getDisplayName()]);
        return $acc->getName();
    }

    /**
     * Sync reports for a date range, by dimension.
     *
     * @return array<string,int> rows synced per dimension
     */
    public function sync(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?: now()->subDays(30);
        $to = $to ?: now();
        $client = $this->oauth->authorizedClient('adsense');
        if (! $client) {
            throw new \RuntimeException('AdSense is not connected.');
        }
        $account = $this->accountName();
        $svc = new Adsense($client);
        $metrics = ['PAGE_VIEWS', 'AD_REQUESTS', 'MATCHED_AD_REQUESTS', 'IMPRESSIONS', 'CLICKS', 'AD_REQUESTS_CTR', 'COST_PER_CLICK', 'PAGE_VIEWS_RPM', 'IMPRESSIONS_RPM', 'AD_REQUESTS_RPM', 'ACTIVE_VIEW_VIEWABILITY', 'ESTIMATED_EARNINGS'];
        $dimensionSets = [
            'date' => ['DATE'],
            'country' => ['DATE', 'COUNTRY_CODE'],
            'platform' => ['DATE', 'PLATFORM_TYPE_NAME'],
            'ad_unit' => ['DATE', 'AD_UNIT_NAME'],
        ];
        $counts = [];
        $token = $this->token();
        try {
            foreach ($dimensionSets as $type => $dims) {
                $params = [
                    'dateRange' => 'CUSTOM',
                    'startDate.year' => $from->year, 'startDate.month' => $from->month, 'startDate.day' => $from->day,
                    'endDate.year' => $to->year, 'endDate.month' => $to->month, 'endDate.day' => $to->day,
                    'metrics' => $metrics,
                    'dimensions' => $dims,
                    'limit' => 10000,
                ];
                $report = $svc->accounts_reports->generate($account, $params);
                $headers = array_map(fn ($h) => $h->getName(), $report->getHeaders() ?: []);
                $currency = 'USD';
                foreach ($report->getHeaders() ?: [] as $h) {
                    if ($h->getName() === 'ESTIMATED_EARNINGS' && $h->getCurrencyCode()) {
                        $currency = $h->getCurrencyCode();
                    }
                }
                $n = 0;
                foreach ($report->getRows() ?: [] as $row) {
                    $cells = array_map(fn ($c) => $c->getValue(), $row->getCells());
                    $data = array_combine($headers, $cells);
                    $date = $data['DATE'] ?? null;
                    if (! $date) {
                        continue;
                    }
                    $value = match ($type) {
                        'country' => $data['COUNTRY_CODE'] ?? '_',
                        'platform' => $data['PLATFORM_TYPE_NAME'] ?? '_',
                        'ad_unit' => $data['AD_UNIT_NAME'] ?? '_',
                        default => '_',
                    };
                    AdsenseReport::updateOrCreate(
                        ['report_date' => $date, 'dimension_type' => $type, 'dimension_value' => mb_substr((string) $value, 0, 190)],
                        [
                            'page_views' => (int) ($data['PAGE_VIEWS'] ?? 0),
                            'ad_requests' => (int) ($data['AD_REQUESTS'] ?? 0),
                            'matched_ad_requests' => (int) ($data['MATCHED_AD_REQUESTS'] ?? 0),
                            'impressions' => (int) ($data['IMPRESSIONS'] ?? 0),
                            'clicks' => (int) ($data['CLICKS'] ?? 0),
                            'ctr' => (float) ($data['AD_REQUESTS_CTR'] ?? 0),
                            'cpc' => (float) ($data['COST_PER_CLICK'] ?? 0),
                            'page_rpm' => (float) ($data['PAGE_VIEWS_RPM'] ?? 0),
                            'impression_rpm' => (float) ($data['IMPRESSIONS_RPM'] ?? 0),
                            'ad_request_rpm' => (float) ($data['AD_REQUESTS_RPM'] ?? 0),
                            'viewability' => (float) ($data['ACTIVE_VIEW_VIEWABILITY'] ?? 0),
                            'earnings' => (float) ($data['ESTIMATED_EARNINGS'] ?? 0),
                            'currency' => $currency,
                            'synced_at' => now(),
                        ]
                    );
                    $n++;
                }
                $counts[$type] = $n;
            }
            $token?->update(['last_synced_at' => now(), 'last_status' => 'ok', 'last_error' => null]);
        } catch (\Throwable $e) {
            $token?->update(['last_status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 2000)]);
            SystemLog::record('adsense_sync', 'AdSense sync failed: '.$e->getMessage(), [], 'error');
            AdminNotification::announce('adsense_sync', 'AdSense sync failed', $e->getMessage(), 'warning', route('admin.monetization.dashboard'));
            throw $e;
        }
        return $counts;
    }

    // ---- Reading ----

    public function totals(Carbon $from, Carbon $to): ?array
    {
        $r = AdsenseReport::where('dimension_type', 'date')->whereBetween('report_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COUNT(*) days, SUM(page_views) page_views, SUM(ad_requests) ad_requests, SUM(matched_ad_requests) matched_ad_requests, SUM(impressions) impressions,
                SUM(clicks) clicks, SUM(earnings) earnings, AVG(viewability) viewability, MAX(currency) currency, MAX(synced_at) synced_at')->first();
        if (! $r || (int) $r->days === 0) {
            return null;
        }
        $t = [
            'page_views' => (int) $r->page_views, 'ad_requests' => (int) $r->ad_requests, 'matched_ad_requests' => (int) $r->matched_ad_requests,
            'impressions' => (int) $r->impressions, 'clicks' => (int) $r->clicks, 'earnings' => (float) $r->earnings,
            'viewability' => (float) $r->viewability, 'currency' => $r->currency ?: 'USD', 'synced_at' => $r->synced_at,
        ];
        $t['ctr'] = $t['ad_requests'] > 0 ? $t['clicks'] / $t['ad_requests'] * 100 : 0;
        $t['cpc'] = $t['clicks'] > 0 ? $t['earnings'] / $t['clicks'] : 0;
        $t['page_rpm'] = $t['page_views'] > 0 ? $t['earnings'] / $t['page_views'] * 1000 : 0;
        $t['impression_rpm'] = $t['impressions'] > 0 ? $t['earnings'] / $t['impressions'] * 1000 : 0;
        $t['ad_request_rpm'] = $t['ad_requests'] > 0 ? $t['earnings'] / $t['ad_requests'] * 1000 : 0;
        $t['coverage'] = $t['ad_requests'] > 0 ? $t['matched_ad_requests'] / $t['ad_requests'] * 100 : 0;
        return $t;
    }

    public function series(Carbon $from, Carbon $to, string $granularity = 'day'): array
    {
        $rows = AdsenseReport::where('dimension_type', 'date')->whereBetween('report_date', [$from->toDateString(), $to->toDateString()])->orderBy('report_date')->get();
        $buckets = [];
        foreach ($rows as $r) {
            $d = $r->report_date;
            $key = match ($granularity) {
                'week' => $d->copy()->startOfWeek()->toDateString(),
                'month' => $d->format('Y-m-01'),
                'year' => $d->format('Y-01-01'),
                default => $d->toDateString(),
            };
            $buckets[$key] = ($buckets[$key] ?? ['earnings' => 0, 'impressions' => 0, 'clicks' => 0, 'page_views' => 0]);
            $buckets[$key]['earnings'] += (float) $r->earnings;
            $buckets[$key]['impressions'] += (int) $r->impressions;
            $buckets[$key]['clicks'] += (int) $r->clicks;
            $buckets[$key]['page_views'] += (int) $r->page_views;
        }
        return [
            'labels' => array_keys($buckets),
            'earnings' => array_map(fn ($b) => round($b['earnings'], 2), array_values($buckets)),
            'impressions' => array_map(fn ($b) => $b['impressions'], array_values($buckets)),
            'clicks' => array_map(fn ($b) => $b['clicks'], array_values($buckets)),
            'page_views' => array_map(fn ($b) => $b['page_views'], array_values($buckets)),
        ];
    }

    public function breakdown(string $type, Carbon $from, Carbon $to, int $limit = 15): array
    {
        return AdsenseReport::where('dimension_type', $type)->whereBetween('report_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('dimension_value value, SUM(page_views) page_views, SUM(ad_requests) ad_requests, SUM(impressions) impressions, SUM(clicks) clicks, SUM(earnings) earnings')
            ->groupBy('dimension_value')->orderByDesc('earnings')->limit($limit)->get()->map(fn ($r) => [
                'value' => $r->value, 'page_views' => (int) $r->page_views, 'ad_requests' => (int) $r->ad_requests, 'impressions' => (int) $r->impressions,
                'clicks' => (int) $r->clicks, 'earnings' => (float) $r->earnings,
                'ctr' => $r->ad_requests > 0 ? $r->clicks / $r->ad_requests * 100 : 0,
                'rpm' => $r->page_views > 0 ? $r->earnings / $r->page_views * 1000 : 0,
            ])->all();
    }

    public function earningsSummary(): array
    {
        $today = now();
        $sum = fn (Carbon $a, Carbon $b) => (float) AdsenseReport::where('dimension_type', 'date')->whereBetween('report_date', [$a->toDateString(), $b->toDateString()])->sum('earnings');
        return [
            'today' => $sum($today, $today),
            'yesterday' => $sum($today->copy()->subDay(), $today->copy()->subDay()),
            'last_7' => $sum($today->copy()->subDays(6), $today),
            'last_30' => $sum($today->copy()->subDays(29), $today),
            'this_month' => $sum($today->copy()->startOfMonth(), $today),
            'last_month' => $sum($today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()),
            'currency' => AdsenseReport::where('dimension_type', 'date')->value('currency') ?: setting('adsense.currency', 'USD'),
        ];
    }

    public function hasData(): bool
    {
        return AdsenseReport::exists();
    }
}
