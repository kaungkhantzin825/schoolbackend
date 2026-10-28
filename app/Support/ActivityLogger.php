<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;

/**
 * Writes the account activity trail.
 *
 * Every call site is inside an auth flow, so this must be incapable of
 * breaking one: exceptions are reported and swallowed, and a log row that
 * fails to write is preferable to a login that fails to complete.
 */
class ActivityLogger
{
    public const LOGIN                    = 'login';
    public const LOGIN_FAILED             = 'login_failed';
    public const LOGOUT                   = 'logout';
    public const PASSWORD_RESET_REQUESTED = 'password_reset_requested';
    public const PASSWORD_RESET           = 'password_reset';
    public const PASSWORD_CHANGED         = 'password_changed';
    public const ACCOUNT_CREATED          = 'account_created';
    public const REGISTRATION_SUBMITTED   = 'registration_submitted';

    /**
     * @param  string       $action  one of the constants above
     * @param  User|null    $user    null for a failed sign-in / public action
     * @param  Request|null $request defaults to the current request
     * @param  array        $extra   description / user_email / user_name overrides
     */
    public static function record(
        string $action,
        ?User $user = null,
        ?Request $request = null,
        array $extra = []
    ): ?UserActivityLog {
        try {
            $request ??= request();

            $ip     = $request?->ip();
            $geo    = IpGeo::locate($ip, $request);
            $agent  = (string) $request?->userAgent();

            return UserActivityLog::create([
                'user_id'      => $user?->id,
                'user_name'    => $user?->name ?? ($extra['user_name'] ?? null),
                'user_email'   => $user?->email ?? ($extra['user_email'] ?? null),
                'role'         => $user?->role ?? ($extra['role'] ?? null),
                'action'       => $action,
                'description'  => $extra['description'] ?? null,
                'ip_address'   => $ip,
                'country'      => $geo['country'],
                'country_code' => $geo['country_code'],
                'city'         => $geo['city'],
                'device'       => self::device($agent),
                'browser'      => self::browser($agent),
                'platform'     => self::platform($agent),
                'user_agent'   => $agent !== '' ? mb_substr($agent, 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /* ── Coarse user-agent parsing ─────────────────────────────────
       Not a browser-detection library on purpose: the log needs
       "phone or laptop, and which browser", nothing finer. */

    private static function device(string $ua): ?string
    {
        if ($ua === '') {
            return null;
        }

        if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua)) {
            return 'Tablet';
        }

        if (preg_match('/Mobile|Android|iPhone|iPod|Windows Phone/i', $ua)) {
            return 'Mobile';
        }

        return 'Desktop';
    }

    private static function browser(string $ua): ?string
    {
        // Order matters: Edge and Opera both claim Chrome, Chrome claims Safari.
        return match (true) {
            $ua === ''                             => null,
            str_contains($ua, 'Edg/')              => 'Edge',
            str_contains($ua, 'OPR/')              => 'Opera',
            str_contains($ua, 'Firefox/')          => 'Firefox',
            str_contains($ua, 'Chrome/')           => 'Chrome',
            str_contains($ua, 'Safari/')           => 'Safari',
            default                                => 'Other',
        };
    }

    private static function platform(string $ua): ?string
    {
        return match (true) {
            $ua === ''                    => null,
            str_contains($ua, 'Windows')  => 'Windows',
            str_contains($ua, 'Android')  => 'Android',
            str_contains($ua, 'iPhone'),
            str_contains($ua, 'iPad'),
            str_contains($ua, 'iPod')     => 'iOS',
            str_contains($ua, 'Mac OS')   => 'macOS',
            str_contains($ua, 'Linux')    => 'Linux',
            default                       => 'Other',
        };
    }
}
