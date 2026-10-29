<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the public SPA base URL used to build links inside emails.
 *
 * Emails are generated server-side, so the backend has to be told where the
 * frontend lives — it cannot infer it. When FRONTEND_URL is left unset the
 * config default (http://localhost:5173) silently produces reset links that
 * work on a developer's machine and nowhere else, which is exactly the bug
 * this class exists to make impossible to ship unnoticed.
 */
class FrontendUrl
{
    /**
     * @param  Request|null  $request  used only to match an allow-listed Origin
     */
    public static function base(?Request $request = null): string
    {
        $configured = self::clean(config('app.frontend_url'));

        // A request Origin is honoured ONLY when it appears in the allow list.
        // Trusting an arbitrary Origin here is the classic password-reset host
        // poisoning hole: an attacker requests a reset for someone else's
        // address with their own Origin and the victim's token lands on the
        // attacker's domain.
        $origin = self::clean($request?->headers->get('Origin'));

        if ($origin !== '' && in_array($origin, self::allowList(), true)) {
            return $origin;
        }

        if ($configured !== '' && !self::isLocal($configured)) {
            return $configured;
        }

        // Past this point the link would point at localhost. In local
        // development that is correct; anywhere else it is a broken email.
        if (!app()->environment('local')) {
            Log::error(
                'FRONTEND_URL is unset or points at localhost — emailed links (password reset, '
                . 'account approval) will not work. Set FRONTEND_URL in the server .env to the '
                . 'public site URL, then run: php artisan config:clear',
                ['frontend_url' => $configured ?: '(unset)', 'app_env' => app()->environment()]
            );
        }

        return $configured !== '' ? $configured : 'http://localhost:5173';
    }

    /** Full URL for a path on the frontend, e.g. path('/login'). */
    public static function path(string $path, ?Request $request = null): string
    {
        return self::base($request) . '/' . ltrim($path, '/');
    }

    /** @return string[] */
    private static function allowList(): array
    {
        return array_values(array_filter(array_map(
            [self::class, 'clean'],
            (array) config('acvr.frontend_urls', [])
        )));
    }

    private static function clean(?string $url): string
    {
        return rtrim(trim((string) $url), '/');
    }

    private static function isLocal(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === ''
            || $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test');
    }
}
