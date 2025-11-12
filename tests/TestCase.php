<?php

namespace GryfOSS\Geolocation\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get the path to a valid GeoIP database file for testing.
     */
    protected function getValidDatabasePath(): string
    {
        return __DIR__ . '/fixtures/GeoLite2-Country.mmdb';
    }

    /**
     * Get the path to a mock database file for testing file existence checks.
     */
    protected function getMockDatabasePath(): string
    {
        return __DIR__ . '/fixtures/mock-geoip.mmdb';
    }

    /**
     * Get the path to a non-existent database file for testing error conditions.
     */
    protected function getInvalidDatabasePath(): string
    {
        return __DIR__ . '/fixtures/non-existent.mmdb';
    }
}