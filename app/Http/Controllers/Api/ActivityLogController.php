<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Account activity trail — Super Admin only (enforced on the route).
 *
 * Read-only by design. An audit log the application can rewrite is not an
 * audit log, so there is no delete or edit endpoint here.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = UserActivityLog::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('user_email', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%");
            });
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($country = $request->input('country')) {
            $query->where('country_code', $country);
        }

        if ($from = $request->input('from')) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $request->input('to')) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        $logs = $query->orderByDesc('id')
            ->paginate(min((int) $request->input('per_page', 50), 200));

        // Append the human label so the frontend never has to map codes.
        $logs->getCollection()->each->append('action_label');

        return response()->json($logs);
    }

    /** Counters for the four summary cards above the table. */
    public function summary()
    {
        $today = Carbon::today();

        return response()->json([
            'logins_today' => UserActivityLog::where('action', ActivityLogger::LOGIN)
                ->where('created_at', '>=', $today)
                ->count(),

            'failed_today' => UserActivityLog::where('action', ActivityLogger::LOGIN_FAILED)
                ->where('created_at', '>=', $today)
                ->count(),

            'active_users_7d' => UserActivityLog::where('action', ActivityLogger::LOGIN)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id'),

            'countries_30d' => UserActivityLog::where('created_at', '>=', Carbon::now()->subDays(30))
                ->whereNotNull('country_code')
                ->distinct()
                ->count('country_code'),

            'total' => UserActivityLog::count(),
        ]);
    }
}
