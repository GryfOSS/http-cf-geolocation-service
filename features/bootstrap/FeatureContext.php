<?php

namespace GryfOSS\Geolocation\Behat;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use GryfOSS\Geolocation\CFGeolocationService;
use PHPUnit\Framework\Assert;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

class FeatureContext implements Context
{
    private CFGeolocationService $service;
    private Request $request;
    private ?string $ipResult = null;
    private ?string $countryResult = null;

    public function __construct()
    {
        $databasePath = __DIR__ . '/../../tests/fixtures/GeoLite2-Country.mmdb';

        if (!file_exists($databasePath)) {
            throw new RuntimeException('GeoLite database missing. Run pull-free-db.sh before executing Behat tests.');
        }

        $this->service = new CFGeolocationService($databasePath);
        $this->request = Request::create('/');
    }

    /**
     * @BeforeScenario
     */
    public function resetScenario(BeforeScenarioScope $scope): void
    {
        $this->request = Request::create('/');
        $this->ipResult = null;
        $this->countryResult = null;
        $this->service->setDebugMode(false);
    }

    /**
     * @Given the request remote address is :ip
     */
    public function theRequestRemoteAddressIs(string $ip): void
    {
        $this->request->server->set('REMOTE_ADDR', $ip);
    }

    /**
     * @Given the request has header :header with value :value
     */
    public function theRequestHasHeaderWithValue(string $header, string $value): void
    {
        $this->request->headers->set($header, $value);
    }

    /**
     * @When I set debug mode to enabled with IP :ip and country :country
     */
    public function iSetDebugModeToEnabledWithIpAndCountry(string $ip, string $country): void
    {
        $this->service->setDebugMode(true, $ip, $country);
    }

    /**
     * @Given debug mode is enabled with IP :ip and country :country
     */
    public function debugModeIsEnabledWithIpAndCountry(string $ip, string $country): void
    {
        $this->service->setDebugMode(true, $ip, $country);
    }

    /**
     * @When I set debug mode to disabled
     */
    public function iSetDebugModeToDisabled(): void
    {
        $this->service->setDebugMode(false);
    }

    /**
     * @When I fetch the client IP
     */
    public function iFetchTheClientIp(): void
    {
        $this->ipResult = $this->service->getIp($this->request);
    }

    /**
     * @When I fetch the country code
     */
    public function iFetchTheCountryCode(): void
    {
        $this->countryResult = $this->service->getCountryCode($this->request);
    }

    /**
     * @Then the resolved IP should be :expected
     */
    public function theResolvedIpShouldBe(string $expected): void
    {
        Assert::assertSame($expected, $this->ipResult ?? '', 'Resolved IP does not match expectation.');
    }

    /**
     * @Then the resolved country should be :expected
     */
    public function theResolvedCountryShouldBe(string $expected): void
    {
        Assert::assertSame($expected, $this->countryResult ?? '', 'Resolved country does not match expectation.');
    }

    /**
     * @Then debug mode should report enabled with IP :ip and country :country
     */
    public function debugModeShouldReportEnabled(string $ip, string $country): void
    {
        Assert::assertTrue($this->service->isDebugModeEnabled(), 'Debug mode expected to be enabled.');
        Assert::assertSame($ip, $this->service->getDebugModeIp(), 'Debug IP mismatch.');
        Assert::assertSame(strtoupper($country), $this->service->getDebugModeCountryCode(), 'Debug country mismatch.');
    }

    /**
     * @Then debug mode should report disabled
     */
    public function debugModeShouldReportDisabled(): void
    {
        Assert::assertFalse($this->service->isDebugModeEnabled(), 'Debug mode expected to be disabled.');
        Assert::assertNull($this->service->getDebugModeIp(), 'Debug IP should be null when disabled.');
        Assert::assertNull($this->service->getDebugModeCountryCode(), 'Debug country should be null when disabled.');
    }
}
