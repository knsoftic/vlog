@extends('layouts.admin')
@section('title', 'Backups')
@section('actions')
    <form method="post" action="{{ route('admin.backups.create') }}">@csrf<input type="hidden" name="type" value="database"><button class="btn-primary">Backup database</button></form>
    <form method="post" action="{{ route('admin.backups.create') }}">@csrf<input type="hidden" name="type" value="media"><button class="btn-secondary">Backup media</button></form>
    <a href="{{ route('admin.settings.edit', 'backup') }}" class="btn-secondary">Schedule settings</a>
@endsection
@section('content')
<p class="alert-info">Automatic backups: database {{ setting_bool('backup.auto_database', true) ? 'on' : 'off' }}, media {{ setting_bool('backup.auto_media') ? 'on' : 'off' }}, {{ setting('backup.frequency', 'daily') }}, keeping {{ setting('backup.keep', 7) }} per type. Files are stored privately in <code>storage/app/private/backups</code>.</p>
<div class="table-wrap"><table class="table">
    <thead><tr><th>File</th><th>Type</th><th>Trigger</th><th>Size</th><th>Status</th><th>Created</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
    @forelse($backups as $b)
        <tr><td class="font-mono text-xs">{{ $b->filename }}</td><td><span class="badge-blue">{{ $b->type }}</span></td><td class="text-xs">{{ $b->trigger }}</td><td>{{ $b->humanSize() }}</td><td><span class="{{ $b->status === 'completed' ? 'badge-green' : ($b->status === 'failed' ? 'badge-red' : 'badge-yellow') }}">{{ $b->status }}</span>@if($b->error)<p class="text-xs text-rose-600">{{ $b->error }}</p>@endif</td><td class="text-xs text-slate-500">{{ $b->created_at->format('M j, Y H:i') }}</td>
            <td class="text-right whitespace-nowrap">
                @if($b->status === 'completed')<a href="{{ route('admin.backups.download', $b) }}" class="btn-secondary btn-sm">Download</a>
                <form method="post" action="{{ route('admin.backups.restore', $b) }}" class="inline" x-data @submit.prevent="if (prompt('Type RESTORE to overwrite current data with this backup:') === 'RESTORE') { $el.querySelector('[name=confirm]').value='RESTORE'; $el.submit(); }">@csrf<input type="hidden" name="confirm" value=""><button class="btn-secondary btn-sm">Restore</button></form>@endif
                <form method="post" action="{{ route('admin.backups.destroy', $b) }}" class="inline" data-confirm="Delete this backup file?">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form>
            </td></tr>
    @empty<tr><td colspan="7" class="py-10 text-center text-slate-400">No backups yet.</td></tr>@endforelse
    </tbody></table></div>
<div class="mt-4">{{ $backups->links() }}</div>
@endsection
