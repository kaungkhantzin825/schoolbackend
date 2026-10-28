<?php

namespace Tests\Unit;

use App\Support\IpGeo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The geolocation helper sits inside the sign-in path, so these tests are
 * mostly about what happens when the lookup *fails*: it must degrade to a
 * null country rather than throw or hang.
 */
class IpGeoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_loopback_is_reported_as_local_network_without_a_lookup(): void
    {
        Http::fake();   // any real request would now fail the test

        $result = IpGeo::locate('127.0.0.1');

        $this->assertSame('Local network', $result['country']);
        $this->assertNull($result['country_code']);
        Http::assertNothingSent();
    }

    public function test_private_range_is_never_geolocated(): void
    {
        Http::fake();

        $this->assertSame('Local network', IpGeo::locate('192.168.1.20')['country']);
        $this->assertSame('Local network', IpGeo::locate('10.0.0.5')['country']);
        Http::assertNothingSent();
    }

    public function test_a_successful_lookup_returns_country_and_city(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'Myanmar',
                'countryCode' => 'MM',
                'city' => 'Yangon',
            ]),
        ]);

        $result = IpGeo::locate('8.8.8.8');

        $this->assertSame('Myanmar', $result['country']);
        $this->assertSame('MM', $result['country_code']);
        $this->assertSame('Yangon', $result['city']);
    }

    public function test_a_provider_failure_degrades_to_unknown(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response(['status' => 'fail'], 200),
        ]);

        $result = IpGeo::locate('1.1.1.1');

        $this->assertNull($result['country']);
        $this->assertNull($result['country_code']);
    }

    public function test_a_transport_error_never_throws(): void
    {
        Http::fake([
            'ip-api.com/*' => fn () => throw new \RuntimeException('offline'),
        ]);

        $result = IpGeo::locate('9.9.9.9');

        $this->assertNull($result['country']);
    }

    public function test_cloudflare_header_is_used_when_the_lookup_is_disabled(): void
    {
        config(['acvr.geo_lookup' => false]);
        Http::fake();

        $request = \Illuminate\Http\Request::create('/login', 'POST', [], [], [], [
            'HTTP_CF_IPCOUNTRY' => 'mm',
            'REMOTE_ADDR' => '203.0.113.9',
        ]);

        $result = IpGeo::locate('203.0.113.9', $request);

        $this->assertSame('MM', $result['country_code']);
        Http::assertNothingSent();
    }

    public function test_a_result_is_cached_so_repeat_sign_ins_do_not_call_out(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'Singapore',
                'countryCode' => 'SG',
                'city' => 'Singapore',
            ]),
        ]);

        IpGeo::locate('8.8.4.4');
        IpGeo::locate('8.8.4.4');

        Http::assertSentCount(1);
    }
}
