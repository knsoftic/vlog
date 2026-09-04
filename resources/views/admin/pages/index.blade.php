@extends('layouts.admin')
@section('title', 'Pages')
@section('actions')<a href="{{ route('admin.pages.create') }}" class="btn-primary">+ New Page</a>@endsection
@section('content')
<div class="table-wrap"><table class="table"><thead><tr><th>Title</th><th>URL</th><th>Template</th><th>Status</th><th>Footer</th><th class="text-right">Views</th><th>Updated</th><th class="text-right">Actions</th></tr></thead><tbody>
@foreach($pages as $p)
    <tr><td class="font-medium">{{ $p->title }} @if($p->is_system)<span class="badge-gray">legal</span>@endif</td><td class="text-xs text-slate-500">/page/{{ $p->slug }}</td><td class="text-xs">{{ $p->template }}</td><td><span class="{{ $p->status === 'published' ? 'badge-green' : 'badge-gray' }}">{{ $p->status }}</span></td><td>{{ $p->show_in_footer ? '✔' : '—' }}</td><td class="text-right">{{ number_format($p->views_count) }}</td><td class="text-xs text-slate-500">{{ $p->updated_at->format('M j, Y') }}</td>
        <td class="text-right whitespace-nowrap"><a href="{{ $p->url }}" target="_blank" class="btn-secondary btn-sm">View</a> <a href="{{ route('admin.pages.edit', $p) }}" class="btn-secondary btn-sm">Edit</a> @unless($p->is_system)<form method="post" action="{{ route('admin.pages.destroy', $p) }}" class="inline" data-confirm="Delete this page?">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form>@endunless</td></tr>
@endforeach
</tbody></table></div>
@endsection
