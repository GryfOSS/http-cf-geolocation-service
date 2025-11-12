<?php

/**
 * CFGeolocationService Usage Examples
 *
 * This file demonstrates various usage scenarios for the CFGeolocationService.
 * Make sure to download the GeoIP database first: ./pull-free-db.sh
 */

require_once __DIR__ . '/vendor/autoload.php';

use GryfOSS\Geolocation\CFGeolocationService;
use Symfony\Component\HttpFoundation\Request;

// Example 1: Basic usage with current request
echo "=== Example 1: Basic Usage ===\n";

try {
    $service = new CFGeolocationService(__DIR__ . '/tests/fixtures/GeoLite2-Country.mmdb');
    $request = Request::createFromGlobals();

    $ip = $service->getIp($request);
    $country = $service->getCountryCode($request);

    echo "Client IP: " . ($ip ?? 'Unknown') . "\n";
    echo "Country Code: " . ($country ?? 'Unknown') . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 2: Simulating Cloudflare headers
echo "=== Example 2: With Cloudflare Headers ===\n";

// Create request with Cloudflare headers
$request = new Request();
$request->headers->set('CF-Connecting-IP', '8.8.8.8');
$request->headers->set('CF-IPCountry', 'US');

try {
    $service = new CFGeolocationService(__DIR__ . '/tests/fixtures/GeoLite2-Country.mmdb');

    $ip = $service->getIp($request);
    $country = $service->getCountryCode($request);

    echo "CF IP: $ip (from CF-Connecting-IP header)\n";
    echo "CF Country: $country (from CF-IPCountry header - no DB lookup!)\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 3: Fallback to GeoIP database
echo "=== Example 3: GeoIP Database Fallback ===\n";

// Create request without Cloudflare headers
$request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);

try {
    $service = new CFGeolocationService(__DIR__ . '/tests/fixtures/GeoLite2-Country.mmdb');

    $ip = $service->getIp($request);
    $country = $service->getCountryCode($request);

    echo "Client IP: $ip (from REMOTE_ADDR)\n";
    echo "Country: $country (from GeoIP database lookup)\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 4: Error handling
echo "=== Example 4: Error Handling ===\n";

try {
    // This will fail - database doesn't exist
    $service = new CFGeolocationService('/nonexistent/database.mmdb');
} catch (InvalidArgumentException $e) {
    echo "Expected error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 5: Testing various IP addresses
echo "=== Example 5: Testing Various IPs ===\n";

$testIPs = [
    '8.8.8.8' => 'Google DNS (US)',
    '1.1.1.1' => 'Cloudflare DNS (AU)',
    '208.67.222.222' => 'OpenDNS (US)',
    '2001:4860:4860::8888' => 'Google DNS IPv6'
];

try {
    $service = new CFGeolocationService(__DIR__ . '/tests/fixtures/GeoLite2-Country.mmdb');

    foreach ($testIPs as $ip => $description) {
        $request = new Request();
        $request->headers->set('CF-Connecting-IP', $ip);

        $detectedIP = $service->getIp($request);
        $country = $service->getCountryCode($request);

        echo sprintf("%-25s | %-15s | %s\n", $description, $detectedIP, $country ?? 'Unknown');
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Did you download the database? Run: ./pull-free-db.sh\n";
}

echo "\n=== Examples completed ===\n";