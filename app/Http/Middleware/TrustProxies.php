<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Null (the default) trusts nothing, which is correct for local
     * development and direct hosting. Behind Cloudflare or a load balancer set
     * TRUSTED_PROXIES — otherwise every request is attributed to the proxy's
     * address in the activity log, and the per-IP rate limiters see one
     * address for the whole internet.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    public function __construct()
    {
        $configured = config('app.trusted_proxies');

        $this->proxies = match (true) {
            blank($configured)  => null,
            $configured === '*' => '*',
            default             => array_map('trim', explode(',', (string) $configured)),
        };
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
