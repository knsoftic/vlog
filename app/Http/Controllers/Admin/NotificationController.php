<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = AdminNotification::latest('created_at')->paginate(30);
        return view('admin.notifications.index', compact('notifications'));
    }

    public function latest()
    {
        return response()->json([
            'unread' => AdminNotification::where('is_read', false)->count(),
            'items' => AdminNotification::latest('created_at')->limit(8)->get(),
        ]);
    }

    public function read(AdminNotification $notification)
    {
        $notification->update(['is_read' => true]);
        return $notification->link ? redirect($notification->link) : back();
    }

    public function readAll()
    {
        AdminNotification::where('is_read', false)->update(['is_read' => true]);
        return back()->with('success', 'All notifications marked as read.');
    }
}
