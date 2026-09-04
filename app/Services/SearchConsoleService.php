<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\GoogleToken;
use App\Models\SearchConsoleReport;
use App\Models\SystemLog;
use Carbon\Carbon;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;

class SearchConsoleService
{
    public function __construct(protected GoogleOAuthService $oauth)
    {
    }

    public function token(): ?GoogleToken
    {
        return GoogleToken::where('service', 'search_console')->first();
    }

    public function isConnected(): bool
    {
        return (bool) $this->token()?->isConnected();
    }

    public function siteUrl(): ?string
    {
        return $this->token()?->account_id ?: (setting('gsc.site_url') ?: null);
    }

    /** List sites available to the connected account. */
    public function listSites(): array
    {
        $client = $this->oauth->authorizedClient('search_console');
        if (! $client) {
            return [];
        }
        $svc = new SearchConsole($client);
        $out = [];
        foreach ($svc->sites->listSites()->getSiteEntry() ?: [] as $s) {
            $out[] = ['url' => $s->getSiteUrl(), 'permission' => $s->getPermissionLevel()];
        }
        return $out;
    }

    public function setSite(string $siteUrl): void
    {
        $this->token()?->update(['account_id' => $siteUrl, 'account_label' => $siteUrl]);
    }

    /** @return array<string,int> */
    public function sync(?Carbon $from = null, ?Carbon $to = null): array
    {
        // GSC data lags ~2-3 days
        $to = $to ?: now()->subDays(2);
        $from = $from ?: $to->copy()->subDays(90);
        $client = $this->oauth->authorizedClient('search_console');
        $site = $this->siteUrl();
        if (! $client || ! $site) {
            throw new \RuntimeException('Search Console is not connected or no property is selected.');
        }
        $svc = new SearchConsole($client);
        $token = $this->token();
        $counts = [];
        try {
            $sets = ['date' => ['date'], 'query' => ['date', 'query'], 'page' => ['date', 'page'], 'country' => ['date', 'country'], 'device' => ['date', 'device']];
            foreach ($sets as $type => $dims) {
                $req = new SearchAnalyticsQueryRequest;
                $req->setStartDate($from->toDateString());
                $req->setEndDate($to->toDateString());
                $req->setDimensions($dims);
                $req->setRowLimit($type === 'date' ? 1000 : 5000);
                $req->setDataState('all');
                $resp = $svc->searchanalytics->query($site, $req);
                $n = 0;
                foreach ($resp->getRows() ?: [] as $row) {
                    $keys = $row->getKeys();
                    $date = $keys[0];
                    $value = $keys[1] ?? '_';
                    SearchConsoleReport::updateOrCreate(
                        ['report_date' => $date, 'dimension_type' => $type, 'dimension_hash' => md5($value)],
                        [
                            'dimension_value' => mb_substr($value, 0, 500),
                            'clicks' => (int) $row->getClicks(),
                            'impressions' => (int) $row->getImpressions(),
                            'ctr' => (float) $row->getCtr(),
                            'position' => (float) $row->getPosition(),
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
            SystemLog::record('gsc_sync', 'Search Console sync failed: '.$e->getMessage(), [], 'error');
            AdminNotification::announce('api_failure', 'Search Console sync failed', $e->getMessage(), 'warning', route('admin.seo.search-console'));
            throw $e;
        }
        return $counts;
    }

    public function totals(Carbon $from, Carbon $to): ?array
    {
        $r = SearchConsoleReport::where('dimension_type', 'date')->whereBetween('report_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COUNT(*) days, SUM(clicks) clicks, SUM(impressions) impressions, AVG(position) position, MAX(synced_at) synced_at')->first();
        if (! $r || (int) $r->days === 0) {
            return null;
        }
        return [
            'clicks' => (int) $r->clicks, 'impressions' => (int) $r->impressions,
            'ctr' => $r->impressions > 0 ? $r->clicks / $r->impressions * 100 : 0,
            'position' => (float) $r->position, 'synced_at' => $r->synced_at,
        ];
    }

    public function series(Carbon $from, Carbon $to): array
    {
        $rows = SearchConsoleReport::where('dimension_type', 'date')->whereBetween('report_date', [$from->toDateString(), $to->toDateString()])->orderBy('report_date')->get();
        return [
            'labels' => $rows->map(fn ($r) => $r->report_date->toDateString())->all(),
            'clicks' => $rows->map(fn ($r) => (int) $r->clicks)->all(),
            'impressions' => $rows->map(fn ($r) => (int) $r->impressions)->all(),
            'position' => $rows->map(fn ($r) => round((float) $r->position, 1))->all(),
        ];
    }

    public function breakdown(string $type, Carbon $from, Carbon $to, int $limit = 20, ?string $filterValue = null): array
    {
        return SearchConsoleReport::where('dimension_type', $type)->whereBetween('report_date', [$from->toDateString(), $to->toDateString()])
            ->when($filterValue, fn ($q) => $q->where('dimension_hash', md5($filterValue)))
            ->selectRaw('dimension_value value, SUM(clicks) clicks, SUM(impressions) impressions, AVG(position) position')
            ->groupBy('dimension_value')->orderByDesc('clicks')->orderByDesc('impressions')->limit($limit)->get()->map(fn ($r) => [
                'value' => $r->value, 'clicks' => (int) $r->clicks, 'impressions' => (int) $r->impressions,
                'ctr' => $r->impressions > 0 ? $r->clicks / $r->impressions * 100 : 0, 'position' => round((float) $r->position, 1),
            ])->all();
    }

    /** Search performance for one page URL. */
    public function pageTotals(string $url, Carbon $from, Carbon $to): ?array
    {
        $r = SearchConsoleReport::where('dimension_type', 'page')->where('dimension_hash', md5($url))
            ->whereBetween('report_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COUNT(*) days, SUM(clicks) clicks, SUM(impressions) impressions, AVG(position) position')->first();
        if (! $r || (int) $r->days === 0) {
            return null;
        }
        return ['clicks' => (int) $r->clicks, 'impressions' => (int) $r->impressions, 'ctr' => $r->impressions > 0 ? $r->clicks / $r->impressions * 100 : 0, 'position' => (float) $r->position];
    }

    public function hasData(): bool
    {
        return SearchConsoleReport::exists();
    }
}
