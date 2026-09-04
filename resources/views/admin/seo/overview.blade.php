@extends('layouts.admin')
@section('title', 'SEO Overview')
@section('actions')@include('admin.partials.range')@endsection
@section('content')
<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    @include('admin.partials.stat', ['label' => 'Google Search Clicks', 'value' => $gscTotals ? compact_number($gscTotals['clicks']) : null, 'unavailable' => !$gscTotals, 'note' => $gscTotals ? number_format($gscTotals['ctr'], 2).'% CTR' : 'Connect Search Console'])
    @include('admin.partials.stat', ['label' => 'Google Search Impressions', 'value' => $gscTotals ? compact_number($gscTotals['impressions']) : null, 'unavailable' => !$gscTotals, 'note' => $gscTotals ? 'Avg position '.number_format($gscTotals['position'], 1) : 'Connect Search Console'])
    @include('admin.partials.stat', ['label' => 'Indexable Posts', 'value' => number_format($indexable), 'note' => $issues['thin'].' thin pages set to noindex'])
    @include('admin.partials.stat', ['label' => '404s (24h)', 'value' => number_format($issues['not_found_24h']), 'note' => $issues['broken_links'].' unresolved broken links'])
</div>
<div class="mt-5 grid gap-5 lg:grid-cols-3">
    <div class="card">
        <h2 class="card-title">Issues</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @foreach([['Missing meta description', $issues['missing_meta']], ['Missing featured image alt text', $issues['missing_alt']], ['Thin content (<100 words, no video)', $issues['thin']], ['No category', $issues['no_category']], ['Title longer than 65 chars', $issues['long_titles']], ['Broken links', $issues['broken_links']]] as [$l, $n])
                <li class="flex items-center justify-between"><span>{{ $l }}</span><span class="{{ $n > 0 ? 'badge-yellow' : 'badge-green' }}">{{ $n }}</span></li>
            @endforeach
        </ul>
        <div class="mt-4 flex flex-wrap gap-2"><a href="{{ route('admin.seo.sitemap') }}" class="btn-secondary btn-sm">Sitemap</a><a href="{{ route('robots') }}" target="_blank" class="btn-secondary btn-sm">robots.txt</a><a href="{{ route('admin.seo.broken-links') }}" class="btn-secondary btn-sm">Broken links</a></div>
    </div>
    <div class="card lg:col-span-2">
        <h2 class="card-title">Posts needing SEO work</h2>
        <table class="table mt-3"><thead><tr><th>Title</th><th class="text-right">Words</th><th>Meta desc</th><th>Robots</th><th></th></tr></thead><tbody>
        @forelse($postsNeedingWork as $p)<tr><td class="font-medium">{{ $p->title }}</td><td class="text-right">{{ $p->word_count }}</td><td>{{ $p->meta_description ? '✔' : '<span class="text-rose-600">missing</span>' }}</td><td class="text-xs">{{ $p->robotsDirective() }}</td><td class="text-right"><a href="{{ route('admin.'.($p->type === 'vlog' ? 'vlogs' : 'articles').'.edit', $p) }}" class="btn-secondary btn-sm">Fix</a></td></tr>
        @empty<tr><td colspan="5" class="text-center text-emerald-600">All published posts have meta descriptions and enough content. 🎉</td></tr>@endforelse
        </tbody></table>
    </div>
</div>
<div class="mt-5 grid gap-5 lg:grid-cols-2">
    <div class="card"><h2 class="card-title">Top queries (Search Console)</h2><table class="table mt-3"><thead><tr><th>Query</th><th class="text-right">Clicks</th><th class="text-right">Impr.</th><th class="text-right">Pos.</th></tr></thead><tbody>@forelse($topQueries as $q)<tr><td>{{ $q['value'] }}</td><td class="text-right">{{ $q['clicks'] }}</td><td class="text-right">{{ number_format($q['impressions']) }}</td><td class="text-right">{{ $q['position'] }}</td></tr>@empty<tr><td colspan="4" class="text-center text-slate-400">Data unavailable — <a href="{{ route('admin.seo.search-console') }}" class="underline">connect Search Console</a>.</td></tr>@endforelse</tbody></table></div>
    <div class="card"><h2 class="card-title">Top pages (Search Console)</h2><table class="table mt-3"><thead><tr><th>Page</th><th class="text-right">Clicks</th><th class="text-right">Impr.</th><th class="text-right">Pos.</th></tr></thead><tbody>@forelse($topPages as $q)<tr><td class="max-w-xs truncate text-xs">{{ $q['value'] }}</td><td class="text-right">{{ $q['clicks'] }}</td><td class="text-right">{{ number_format($q['impressions']) }}</td><td class="text-right">{{ $q['position'] }}</td></tr>@empty<tr><td colspan="4" class="text-center text-slate-400">Data unavailable.</td></tr>@endforelse</tbody></table></div>
</div>
@endsection
