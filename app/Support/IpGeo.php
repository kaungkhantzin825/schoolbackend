<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Best-effort country lookup for a client IP.
 *
 * Two constraints shaped this class:
 *
 *  1. A sign-in must never wait on the network. The lookup runs with a 2s
 *     timeout and every failure is swallowed — a missing country is a cosmetic
 *     gap, a blocked login is an outage.
 *  2. Nothing here may throw. It is called from inside the auth path.
 *
 * Results are cached per IP for a day: the same office NAT address signs in
 * dozens of times a day and the answer does not change.
 */
class IpGeo
{
    private const CACHE_TTL = 86400;      // 24h
    private const TIMEOUT   = 2;          // seconds

    /**
     * @return array{country: ?string, country_code: ?string, city: ?string}
     */
    public static function locate(?string $ip, ?Request $request = null): array
    {
        $ip = self::normalise($ip);

        if ($ip === null) {
            return self::unknown();
        }

        // Loopback and RFC1918 addresses are the norm in local development and
        // on an internal network. Say so plainly rather than inventing a
        // country for 127.0.0.1.
        if (!self::isPublic($ip)) {
            return ['country' => 'Local network', 'country_code' => null, 'city' => null];
        }

        $cached = Cache::get(self::cacheKey($ip));
        if (is_array($cached)) {
            return $cached;
        }

        $result = self::resolve($ip, $request);

        // Only cache a real answer; caching "Unknown" would freeze a transient
        // outage into the log for a day.
        if ($result['country'] !== null) {
            Cache::put(self::cacheKey($ip), $result, self::CACHE_TTL);
        }

        return $result;
    }

    /**
     * @return array{country: ?string, country_code: ?string, city: ?string}
     */
    private static function resolve(string $ip, ?Request $request): array
    {
        if (!config('acvr.geo_lookup', true)) {
            return self::fromCloudflareHeader($request) ?? self::unknown();
        }

        return self::lookup($ip) ?? self::fromCloudflareHeader($request) ?? self::unknown();
    }

    /** Strip IPv6-mapped IPv4 prefixes and whitespace. */
    private static function normalise(?string $ip): ?string
    {
        $ip = trim((string) $ip);

        if ($ip === '') {
            return null;
        }

        if (str_starts_with($ip, '::ffff:')) {
            $ip = substr($ip, 7);
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    private static function isPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private static function cacheKey(string $ip): string
    {
        return 'geo:ip:' . $ip;
    }

    /**
     * @return array{country: ?string, country_code: ?string, city: ?string}|null
     */
    private static function lookup(string $ip): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->acceptJson()
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,country,countryCode,city',
                ]);

            if (!$response->ok()) {
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? null) !== 'success') {
                return null;
            }

            return [
                'country'      => $data['country'] ?? null,
                'country_code' => $data['countryCode'] ?? null,
                'city'         => $data['city'] ?? null,
            ];
        } catch (\Throwable $e) {
            // Offline server, DNS failure, provider down — all non-fatal.
            return null;
        }
    }

    /**
     * Cloudflare stamps every proxied request with the client's country. Used
     * only as a fallback: the header carries a code but no readable name.
     */
    private static function fromCloudflareHeader(?Request $request): ?array
    {
        $code = strtoupper((string) $request?->header('CF-IPCountry'));

        if (!preg_match('/^[A-Z]{2}$/', $code) || $code === 'XX') {
            return null;
        }

        return ['country' => $code, 'country_code' => $code, 'city' => null];
    }

    private static function unknown(): array
    {
        return ['country' => null, 'country_code' => null, 'city' => null];
    }
}
