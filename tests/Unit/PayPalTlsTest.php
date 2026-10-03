<?php

namespace Tests\Unit;

use App\Services\PayPalService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalTlsTest extends TestCase
{
    public function test_authentication_and_api_requests_keep_tls_verification_enabled(): void
    {
        config(['cache.default' => 'array', 'payments.paypal.client_id' => 'tls-test-client',
            'payments.paypal.client_secret' => 'test-secret',
            'payments.paypal.base_url' => 'https://api-m.sandbox.paypal.com']);
        Cache::forget('paypal:access-token:'.sha1('https://api-m.sandbox.paypal.com|tls-test-client'));
        Http::preventStrayRequests();
        $seen = [];
        Http::fake(function ($request, $options) use (&$seen) {
            $seen[] = $options;
            return str_ends_with($request->url(), '/v1/oauth2/token')
                ? Http::response(['access_token' => 'test-token', 'expires_in' => 300])
                : Http::response(['plans' => []]);
        });

        $response = app(PayPalService::class)->client()->get('/v1/billing/plans');

        $this->assertTrue($response->successful());
        $this->assertCount(2, $seen);
        foreach ($seen as $options) {
            $this->assertTrue($options['verify']);
            if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
                $this->assertSame(CURLSSLOPT_NATIVE_CA, $options['curl'][CURLOPT_SSL_OPTIONS]);
            }
        }
        Http::assertSentCount(2);
    }
}
