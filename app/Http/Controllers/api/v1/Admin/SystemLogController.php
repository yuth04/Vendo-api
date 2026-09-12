<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemLogController extends Controller
{

    /**
     * Display all system logs with optional search & filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SystemLog::with(['user:id,first_name,last_name,email,role,image,last_login_at'])
            ->latest(); // Order by newest logs first

        // Search across description, action, or module
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('description', 'like', '%' . $request->search . '%')
                    ->orWhere('action', 'like', '%' . $request->search . '%')
                    ->orWhere('module', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by module
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            return response()->json([
                'message' => 'No logs found.',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'Retrieved logs successfully.',
            'data' => $logs,
        ], 200);
    }

    /**
     * Get single log detail.
     */
    public function show($id): JsonResponse
    {
        $log = SystemLog::with('user:id,first_name,last_name,email')->find($id);

        if (!$log) {
            return response()->json(['message' => 'System log record not found'], 404);
        }

        return response()->json([
            'message' => 'System log details retrieved',
            'data' => $log,
        ]);
    }

    /**
     * Get basic summary statistics for logs (dashboard view).
     */
    public function statistics(): JsonResponse
    {
        return response()->json([
            'total_logs' => SystemLog::count(),
            'today_logs' => SystemLog::whereDate('created_at', now()->today())->count(),
            'by_module' => SystemLog::selectRaw('module, count(*) as count')->groupBy('module')->pluck('count', 'module'),
        ]);
    }

    /**
     * Delete a specific log record.
     */
    public function destroy($id): JsonResponse
    {
        if (auth()->user()->role !== 'super-admin') {
            return response()->json(['message' => 'Only super-admin can delete system logs.'], 403);
        }

        $log = SystemLog::find($id);

        if (!$log) {
            return response()->json(['message' => 'Log record not found'], 404);
        }

        $log->delete();

        return response()->json(['message' => 'Log deleted successfully']);
    }

    /**
     * Clear old logs (e.g., older than 30 days).
     */
    public function clear(Request $request): JsonResponse
    {
        if (auth()->user()->role !== 'super-admin') {
            return response()->json(['message' => 'Only super-admin can clear logs.'], 403);
        }

        $days = $request->get('days', 30);
        $deletedCount = SystemLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'message' => "Successfully cleared logs older than {$days} days.",
            'deleted_count' => $deletedCount,
        ]);
    }
}