<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\JobRun;
use App\Models\SecurityLog;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogController extends Controller
{
    public function admin(Request $request)
    {
        $q = AdminLog::with('user')->latest('created_at');
        if ($request->filled('user')) {
            $q->where('user_id', (int) $request->user);
        }
        if ($request->filled('action')) {
            $q->where('action', $request->action);
        }
        if ($request->filled('module')) {
            $q->where('module', $request->module);
        }
        if ($request->filled('from')) {
            $q->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->where('created_at', '<=', $request->to.' 23:59:59');
        }
        $logs = $q->paginate(40)->withQueryString();
        $actions = AdminLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $modules = AdminLog::select('module')->distinct()->whereNotNull('module')->orderBy('module')->pluck('module');
        $users = User::orderBy('name')->get(['id', 'name']);
        return view('admin.logs.admin', compact('logs', 'actions', 'modules', 'users'));
    }

    public function security(Request $request)
    {
        $q = SecurityLog::latest('created_at');
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }
        if ($request->filled('severity')) {
            $q->where('severity', $request->severity);
        }
        $logs = $q->paginate(40)->withQueryString();
        $types = SecurityLog::select('type')->distinct()->orderBy('type')->pluck('type');
        $summary = [
            'failed_24h' => SecurityLog::where('type', 'failed_login')->where('created_at', '>=', now()->subDay())->count(),
            'blocked_24h' => SecurityLog::whereIn('type', ['bot_blocked', 'rate_limited'])->where('created_at', '>=', now()->subDay())->count(),
            'critical_7d' => SecurityLog::where('severity', 'critical')->where('created_at', '>=', now()->subDays(7))->count(),
        ];
        return view('admin.logs.security', compact('logs', 'types', 'summary'));
    }

    public function system(Request $request, MediaService $media)
    {
        $q = SystemLog::latest('last_seen_at');
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }
        $logs = $q->paginate(40)->withQueryString();
        $types = SystemLog::select('type')->distinct()->orderBy('type')->pluck('type');
        $summary = [
            '404_24h' => (int) SystemLog::where('type', '404')->where('last_seen_at', '>=', now()->subDay())->sum('occurrences'),
            '500_24h' => (int) SystemLog::whereIn('type', ['500', 'exception'])->where('last_seen_at', '>=', now()->subDay())->sum('occurrences'),
            'api_failures_7d' => (int) SystemLog::whereIn('type', ['api_failure', 'adsense_sync', 'gsc_sync', 'analytics_sync'])->where('last_seen_at', '>=', now()->subDays(7))->sum('occurrences'),
            'broken_links' => \App\Models\BrokenLink::where('is_resolved', false)->count(),
        ];
        $jobs = JobRun::latest('started_at')->limit(30)->get();
        $lastRuns = JobRun::selectRaw('name, MAX(started_at) last_at')->groupBy('name')->get()->keyBy('name');
        $expectedJobs = ['posts:publish-scheduled', 'analytics:aggregate', 'analytics:retention', 'adsense:sync', 'search_console:sync', 'links:check', 'backup:run'];
        $storage = $media->storageUsage();
        $db = ['status' => 'ok', 'size' => null, 'tables' => null, 'version' => null];
        try {
            $db['version'] = DB::select('SELECT VERSION() v')[0]->v ?? null;
            $row = DB::selectOne('SELECT COUNT(*) tables, SUM(data_length + index_length) size FROM information_schema.TABLES WHERE table_schema = ?', [config('database.connections.mysql.database')]);
            $db['size'] = (int) ($row->size ?? 0);
            $db['tables'] = (int) ($row->tables ?? 0);
        } catch (\Throwable $e) {
            $db['status'] = 'error: '.$e->getMessage();
        }
        $schedulerOk = JobRun::where('started_at', '>=', now()->subMinutes(15))->exists();
        return view('admin.logs.system', compact('logs', 'types', 'summary', 'jobs', 'lastRuns', 'expectedJobs', 'storage', 'db', 'schedulerOk'));
    }

    public function clearSystem(Request $request)
    {
        $type = $request->input('type');
        $q = SystemLog::query();
        if ($type) {
            $q->where('type', $type);
        }
        $n = $q->delete();
        app(\App\Services\AuditLogger::class)->log('logs_cleared', 'system', null, "Cleared {$n} system log entries".($type ? " ({$type})" : ''));
        return back()->with('success', "Cleared {$n} entries.");
    }
}
