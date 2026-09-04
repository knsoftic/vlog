<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\AuditLogger;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function __construct(protected BackupService $backups, protected AuditLogger $audit)
    {
    }

    public function index()
    {
        $backups = Backup::latest()->paginate(20);
        return view('admin.backups.index', compact('backups'));
    }

    public function create(Request $request)
    {
        $type = $request->input('type') === 'media' ? 'media' : 'database';
        try {
            set_time_limit(600);
            $b = $type === 'media' ? $this->backups->media('manual', auth()->id()) : $this->backups->database('manual', auth()->id());
            $this->audit->log('backup_created', 'backup', $b, ucfirst($type).' backup created ('.$b->humanSize().')');
            return back()->with('success', ucfirst($type).' backup created ('.$b->humanSize().').');
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => 'Backup failed: '.$e->getMessage()]);
        }
    }

    public function download(Backup $backup)
    {
        abort_unless(Storage::disk($backup->disk)->exists($backup->path), 404);
        $this->audit->log('backup_downloaded', 'backup', $backup, 'Downloaded '.$backup->filename);
        return Storage::disk($backup->disk)->download($backup->path, $backup->filename);
    }

    public function restore(Request $request, Backup $backup)
    {
        $request->validate(['confirm' => 'required|in:RESTORE']);
        try {
            set_time_limit(600);
            // safety: snapshot the current DB before restoring
            if ($backup->type === 'database') {
                $this->backups->database('manual', auth()->id());
                $this->backups->restoreDatabase($backup);
            } else {
                $this->backups->restoreMedia($backup);
            }
            $this->audit->log('backup_restored', 'backup', $backup, 'Restored '.$backup->filename);
            return back()->with('success', 'Backup restored. A safety snapshot of the previous state was created first.');
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => 'Restore failed: '.$e->getMessage()]);
        }
    }

    public function destroy(Backup $backup)
    {
        Storage::disk($backup->disk)->delete($backup->path);
        $this->audit->log('backup_deleted', 'backup', $backup, 'Deleted '.$backup->filename);
        $backup->delete();
        return back()->with('success', 'Backup deleted.');
    }
}
