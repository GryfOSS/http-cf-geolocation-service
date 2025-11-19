<?php

namespace GryfOSS\Geolocation\Tests\Unit;

use GryfOSS\Geolocation\CFGeolocationService;
use GryfOSS\Geolocation\Tests\TestCase;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Exception;

class CFGeolocationServiceTest extends TestCase
{
    private string $validDatabasePath;
    private string $invalidDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validDatabasePath = $this->getValidDatabasePath();
        $this->invalidDatabasePath = $this->getInvalidDatabasePath();
    }

    public function testConstructorWithValidDatabasePath(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $this->assertInstanceOf(CFGeolocationService::class, $service);
    }

    public function testConstructorWithInvalidDatabasePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Database file not found: " . $this->invalidDatabasePath);

        new CFGeolocationService($this->invalidDatabasePath);
    }

    public function testGetIpWithValidCloudflareHeader(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request();
        $request->headers->set('CF-Connecting-IP', '192.168.1.1');

        $result = $service->getIp($request);

        $this->assertEquals('192.168.1.1', $result);
    }

    public function testGetIpWithInvalidCloudflareHeaderFallsBackToClientIp(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        // Create a request with invalid CF-Connecting-IP but valid REMOTE_ADDR
        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '203.0.113.1']);
        $request->headers->set('CF-Connecting-IP', 'invalid-ip');

        $result = $service->getIp($request);

        $this->assertEquals('203.0.113.1', $result);
    }

    public function testGetIpWithoutCloudflareHeaderUsesClientIp(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '198.51.100.1']);

        $result = $service->getIp($request);

        $this->assertEquals('198.51.100.1', $result);
    }

    public function testGetIpWithIPv6Address(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request();
        $request->headers->set('CF-Connecting-IP', '2001:db8::1');

        $result = $service->getIp($request);

        $this->assertEquals('2001:db8::1', $result);
    }

    public function testGetCountryCodeWithValidCloudflareHeader(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request();
        $request->headers->set('CF-IPCountry', 'US');

        $result = $service->getCountryCode($request);

        $this->assertEquals('US', $result);
    }

    public function testGetCountryCodeWithInvalidCloudflareCountryHeader(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request();
        $request->headers->set('CF-IPCountry', 'InvalidCountry');
        $request->headers->set('CF-Connecting-IP', '8.8.8.8');

        // Should fallback to GeoIP lookup since 'InvalidCountry' doesn't match /^[A-Z]{2}$/
        // With real database and valid IP (8.8.8.8 - Google DNS), it should return 'US'
        $result = $service->getCountryCode($request);

        $this->assertEquals('US', $result);
    }

    public function testGetCountryCodeWithLowercaseCountryCodeInHeader(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request();
        $request->headers->set('CF-IPCountry', 'us'); // lowercase
        $request->headers->set('CF-Connecting-IP', '8.8.8.8');

        // Should fallback to GeoIP lookup since 'us' doesn't match /^[A-Z]{2}$/
        // With real database and valid IP (8.8.8.8 - Google DNS), it should return 'US'
        $result = $service->getCountryCode($request);

        $this->assertEquals('US', $result);
    }

    public function testGetCountryCodeWithSingleCharacterCountryCode(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request();
        $request->headers->set('CF-IPCountry', 'U'); // single character
        $request->headers->set('CF-Connecting-IP', '8.8.8.8');

        // Should fallback to GeoIP lookup since 'U' doesn't match /^[A-Z]{2}$/
        // With real database and valid IP (8.8.8.8 - Google DNS), it should return 'US'
        $result = $service->getCountryCode($request);

        $this->assertEquals('US', $result);
    }

    public function testGetCountryCodeWithThreeCharacterCountryCode(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request();
        $request->headers->set('CF-IPCountry', 'USA'); // three characters
        $request->headers->set('CF-Connecting-IP', '8.8.8.8');

        // Should fallback to GeoIP lookup since 'USA' doesn't match /^[A-Z]{2}$/
        // With real database and valid IP (8.8.8.8 - Google DNS), it should return 'US'
        $result = $service->getCountryCode($request);

        $this->assertEquals('US', $result);
    }

    public function testGetCountryCodeFallbackToGeoIpWhenNoCloudflareHeaders(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);

        // Should use GeoIP lookup with real database
        // 8.8.8.8 (Google DNS) should return 'US'
        $result = $service->getCountryCode($request);

        $this->assertEquals('US', $result);
    }

    /**
     * @dataProvider validCountryCodeProvider
     */
    public function testValidCountryCodesInCloudflareHeader(string $countryCode): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request();
        $request->headers->set('CF-IPCountry', $countryCode);

        $result = $service->getCountryCode($request);

        $this->assertEquals($countryCode, $result);
    }

    public static function validCountryCodeProvider(): array
    {
        return [
            ['US'],
            ['GB'],
            ['DE'],
            ['FR'],
            ['CA'],
            ['AU'],
            ['JP'],
            ['BR'],
            ['IN'],
            ['CN'],
        ];
    }

    /**
     * @dataProvider invalidIpAddressProvider
     */
    public function testGetIpWithInvalidIpAddressesInCloudflareHeader(string $invalidIp): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '203.0.113.1']);
        $request->headers->set('CF-Connecting-IP', $invalidIp);

        $result = $service->getIp($request);

        // Should fall back to client IP when CF-Connecting-IP is invalid
        $this->assertEquals('203.0.113.1', $result);
    }

    public static function invalidIpAddressProvider(): array
    {
        return [
            ['not-an-ip'],
            ['999.999.999.999'],
            ['192.168.1'],
            ['192.168.1.1.1'],
            [''],
            ['abc.def.ghi.jkl'],
            ['256.1.1.1'],
            ['192.168.-1.1'],
        ];
    }

    public function testGetCountryCodeWithEmptyCloudflareCountryHeader(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request();
        $request->headers->set('CF-IPCountry', '');
        $request->headers->set('CF-Connecting-IP', '8.8.8.8');

        // Should fallback to GeoIP lookup since empty string doesn't match pattern
        // With real database and valid IP (8.8.8.8 - Google DNS), it should return 'US'
        $result = $service->getCountryCode($request);

        $this->assertEquals('US', $result);
    }

    public function testGetCountryCodeWithNullCloudflareCountryHeader(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $request = new Request();
        // Don't set CF-IPCountry header at all (null case)
        $request->headers->set('CF-Connecting-IP', '8.8.8.8');

        // Should fallback to GeoIP lookup since null doesn't match pattern
        // With real database and valid IP (8.8.8.8 - Google DNS), it should return 'US'
        $result = $service->getCountryCode($request);

        $this->assertEquals('US', $result);
    }

    public function testGetCountryCodeExceptionHandling(): void
    {
        // Create a mock service that will throw an exception in getIp
        $service = new class($this->validDatabasePath) extends CFGeolocationService {
            public function getIp(\Symfony\Component\HttpFoundation\Request $request): ?string
            {
                throw new \RuntimeException("Mock exception for testing");
            }
        };

        $request = new Request();
        // Set invalid CF-IPCountry to trigger fallback to getIp
        $request->headers->set('CF-IPCountry', 'invalid');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Unable to determine client IP address.");

        $service->getCountryCode($request);
    }

    public function testSetDebugModeOverridesResponses(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);
        $service->setDebugMode(true, '203.0.113.10', 'pl');

        $request = new Request();
        $request->headers->set('CF-Connecting-IP', '8.8.8.8');
        $request->headers->set('CF-IPCountry', 'US');

        $this->assertEquals('203.0.113.10', $service->getIp($request));
        $this->assertEquals('PL', $service->getCountryCode($request));
        $this->assertTrue($service->isDebugModeEnabled());
        $this->assertEquals('203.0.113.10', $service->getDebugModeIp());
        $this->assertEquals('PL', $service->getDebugModeCountryCode());
    }

    public function testDebugModeIgnoresRequestData(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);
        $service->setDebugMode(true, '198.51.100.5', 'gb');

        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);
        $request->headers->set('CF-Connecting-IP', 'invalid');
        $request->headers->set('CF-IPCountry', 'X1');

        $this->assertEquals('198.51.100.5', $service->getIp($request));
        $this->assertEquals('GB', $service->getCountryCode($request));
    }

    public function testDisablingDebugModeRestoresNormalBehavior(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);
        $service->setDebugMode(true, '203.0.113.10', 'PL');
        $service->setDebugMode(false);

        $request = new Request();
        $request->headers->set('CF-Connecting-IP', '8.8.4.4');
        $request->headers->set('CF-IPCountry', 'US');

        $this->assertEquals('8.8.4.4', $service->getIp($request));
        $this->assertEquals('US', $service->getCountryCode($request));
        $this->assertFalse($service->isDebugModeEnabled());
        $this->assertNull($service->getDebugModeIp());
        $this->assertNull($service->getDebugModeCountryCode());
    }

    public function testSetDebugModeValidatesIp(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid debug IP address.');

        $service->setDebugMode(true, 'not-an-ip', 'US');
    }

    public function testSetDebugModeValidatesCountry(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid debug country code. Expected ISO 3166-1 alpha-2.');

        $service->setDebugMode(true, '203.0.113.10', 'USA');
    }

    public function testSetDebugModeWithIpv6(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);
        $ipv6 = '2001:db8::1';

        $service->setDebugMode(true, $ipv6, 'DE');

        $request = new Request();

        $this->assertEquals($ipv6, $service->getIp($request));
        $this->assertEquals('DE', $service->getCountryCode($request));
    }

    public function testSetDebugModeDisabledIgnoresOptionalArgs(): void
    {
        $service = new CFGeolocationService($this->validDatabasePath);

        $service->setDebugMode(false, '198.51.100.5', 'GB');

        $request = new Request();
        $request->headers->set('CF-Connecting-IP', '8.8.8.8');
        $request->headers->set('CF-IPCountry', 'US');

        $this->assertEquals('8.8.8.8', $service->getIp($request));
        $this->assertEquals('US', $service->getCountryCode($request));
        $this->assertFalse($service->isDebugModeEnabled());
        $this->assertNull($service->getDebugModeIp());
        $this->assertNull($service->getDebugModeCountryCode());
    }
}