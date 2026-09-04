<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $title }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
    h1 { font-size: 18px; margin: 0 0 4px; } h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
    .meta { color: #64748b; font-size: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    th, td { border: 1px solid #e2e8f0; padding: 4px 6px; text-align: left; vertical-align: top; word-break: break-word; }
    th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
    tr:nth-child(even) td { background: #f8fafc; }
</style></head>
<body>
<h1>{{ $title }}</h1>
<p class="meta">{{ $site }} · generated {{ now()->format('Y-m-d H:i') }}</p>
@foreach($sections as $s)
    <h2>{{ $s['title'] }}</h2>
    <table><thead><tr>@foreach($s['headers'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse(array_slice($s['rows'], 0, 300) as $r)<tr>@foreach($r as $v)<td>{{ is_scalar($v) || $v === null ? $v : json_encode($v) }}</td>@endforeach</tr>
    @empty<tr><td colspan="{{ count($s['headers']) }}">No data</td></tr>@endforelse
    </tbody></table>
    @if(count($s['rows']) > 300)<p class="meta">Showing first 300 of {{ count($s['rows']) }} rows — use CSV/Excel for the full list.</p>@endif
@endforeach
</body></html>
