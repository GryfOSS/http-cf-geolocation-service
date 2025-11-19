<?php

namespace GryfOSS\Geolocation\Tests\Feature;

use GryfOSS\Geolocation\CFGeolocationService;
use GryfOSS\Geolocation\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Exception;

/**
 * Integration tests that test the full functionality of CFGeolocationService
 * with real GeoIP database (if available) or mock behaviors.
 */
class CFGeolocationServiceIntegrationTest extends TestCase
{
    private CFGeolocationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CFGeolocationService($this->getValidDatabasePath());
    }

    public function testCompleteWorkflowWithCloudflareHeaders(): void
    {
        $request = new Request();
        $request->headers->set('CF-Connecting-IP', '8.8.8.8');
        $request->headers->set('CF-IPCountry', 'US');

        $ip = $this->service->getIp($request);
        $country = $this->service->getCountryCode($request);

        $this->assertEquals('8.8.8.8', $ip);
        $this->assertEquals('US', $country);
    }

    public function testCompleteWorkflowFallbackBehavior(): void
    {
        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '203.0.113.1']);
        // No Cloudflare headers set

        $ip = $this->service->getIp($request);

        $this->assertEquals('203.0.113.1', $ip);

        // Country lookup will fail with mock database, but that's expected behavior
        try {
            $this->service->getCountryCode($request);
            $this->fail('Expected Exception when using mock database for GeoIP lookup');
        } catch (Exception $e) {
            $this->addToAssertionCount(1); // Count this as a successful assertion
        }
    }

    public function testMixedValidAndInvalidHeaders(): void
    {
        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '198.51.100.1']);
        $request->headers->set('CF-Connecting-IP', 'invalid-ip'); // Invalid IP
        $request->headers->set('CF-IPCountry', 'US'); // Valid country

        $ip = $this->service->getIp($request);
        $country = $this->service->getCountryCode($request);

        // Should fall back to REMOTE_ADDR for IP
        $this->assertEquals('198.51.100.1', $ip);
        // Should use Cloudflare country header
        $this->assertEquals('US', $country);
    }

    public function testIPv6SupportInCloudflareHeaders(): void
    {
        $request = new Request();
        $request->headers->set('CF-Connecting-IP', '2001:db8:85a3::8a2e:370:7334');
        $request->headers->set('CF-IPCountry', 'CA');

        $ip = $this->service->getIp($request);
        $country = $this->service->getCountryCode($request);

        $this->assertEquals('2001:db8:85a3::8a2e:370:7334', $ip);
        $this->assertEquals('CA', $country);
    }

    public function testDebugModeOverridesRequestData(): void
    {
        $this->service->setDebugMode(true, '203.0.113.77', 'NL');

        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '198.51.100.1']);
        $request->headers->set('CF-Connecting-IP', '8.8.8.8');
        $request->headers->set('CF-IPCountry', 'US');

        $this->assertEquals('203.0.113.77', $this->service->getIp($request));
        $this->assertEquals('NL', $this->service->getCountryCode($request));
    }

    public function testDisablingDebugModeRestoresDetection(): void
    {
        $this->service->setDebugMode(true, '203.0.113.77', 'NL');
        $this->service->setDebugMode(false);

        $request = new Request();
        $request->headers->set('CF-Connecting-IP', '9.9.9.9');
        $request->headers->set('CF-IPCountry', 'FR');

        $this->assertEquals('9.9.9.9', $this->service->getIp($request));
        $this->assertEquals('FR', $this->service->getCountryCode($request));
    }

    public function testEdgeCaseCountryCodes(): void
    {
        // Test valid country codes that match the pattern /^[A-Z]{2}$/
        $validCodes = [
            'XX', // Unknown/unspecified country code
        ];

        foreach ($validCodes as $countryCode) {
            $request = new Request();
            $request->headers->set('CF-IPCountry', $countryCode);

            $result = $this->service->getCountryCode($request);

            $this->assertEquals($countryCode, $result, "Failed for country code: {$countryCode}");
        }

        // Test invalid country codes that should fallback to GeoIP
        $invalidCodes = [
            'T1', // Tor exit nodes (contains digit)
            'A1', // Anonymous proxy (contains digit)
            'A2', // Satellite provider (contains digit)
        ];

        foreach ($invalidCodes as $countryCode) {
            $request = new Request();
            $request->headers->set('CF-IPCountry', $countryCode);
            $request->headers->set('CF-Connecting-IP', '8.8.8.8'); // Google DNS

            // Should fallback to GeoIP lookup since these don't match /^[A-Z]{2}$/
            $result = $this->service->getCountryCode($request);

            $this->assertEquals('US', $result, "Expected fallback to GeoIP for: {$countryCode}");
        }
    }
}