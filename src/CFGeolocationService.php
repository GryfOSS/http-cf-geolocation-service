<?php

namespace GryfOSS\Geolocation;

use Exception;
use GeoIp2\Database\Reader;
use Symfony\Component\HttpFoundation\Request;

/**
 * CFGeolocationService - A service for detecting user geolocation using Cloudflare headers and GeoIP database
 *
 * This service prioritizes Cloudflare's geo-location headers for performance and accuracy,
 * falling back to MaxMind GeoIP2 database lookups when Cloudflare headers are unavailable
 * or invalid. It provides methods to extract client IP addresses and determine country codes.
 *
 * @package GryfOSS\Geolocation
 * @author IDCT Bartosz Pachołek
 * @since 1.0.0
 */
class CFGeolocationService
{
    /**
     * Cloudflare header containing the real client IP address
     * This header bypasses any proxy or load balancer IPs
     */
    protected const CF_IP_HEADER = 'CF-Connecting-IP';

    /**
     * Cloudflare header containing the detected country code (ISO 3166-1 alpha-2)
     * This provides fast country detection without database lookups
     */
    protected const CF_COUNTRY_HEADER = 'CF-IPCountry';

    /**
     * Initialize the geolocation service with a GeoIP database
     *
     * @param string $databasePath Path to the MaxMind GeoIP2 database file (.mmdb)
     * @throws \InvalidArgumentException If the database file doesn't exist
     */
    public function __construct(protected string $databasePath)
    {
        // Validate that the GeoIP database file exists before proceeding
        if (!file_exists($this->databasePath)) {
            throw new \InvalidArgumentException("Database file not found: " . $this->databasePath);
        }
    }

    /**
     * Extract the real client IP address from the HTTP request
     *
     * This method prioritizes Cloudflare's CF-Connecting-IP header which contains
     * the original client IP before passing through Cloudflare's proxy network.
     * Falls back to Symfony's getClientIp() if CF header is missing or invalid.
     *
     * @param Request $request The HTTP request object
     * @return string|null The client IP address, or null if unavailable
     */
    public function getIp(Request $request): ?string
    {
        // Extract the IP from Cloudflare's CF-Connecting-IP header
        $cfIp = $request->headers->get(self::CF_IP_HEADER);

        // Validate that the CF IP is a properly formatted IP address (IPv4 or IPv6)
        if (filter_var($cfIp, FILTER_VALIDATE_IP)) {
            return $cfIp;
        }

        // Fall back to Symfony's client IP detection if CF header is missing/invalid
        return $request->getClientIp();
    }

    /**
     * Determine the country code (ISO 3166-1 alpha-2) for the client request
     *
     * This method uses a two-tier approach for country detection:
     * 1. First, it checks Cloudflare's CF-IPCountry header for instant results
     * 2. If CF header is missing/invalid, it falls back to GeoIP database lookup
     *
     * The CF-IPCountry header is validated against ISO 3166-1 alpha-2 format (exactly 2 uppercase letters).
     *
     * @param Request $request The HTTP request object
     * @return string|null The ISO 3166-1 alpha-2 country code (e.g., 'US', 'GB'), or null if unavailable
     * @throws Exception If IP address cannot be determined or GeoIP lookup fails
     */
    public function getCountryCode(Request $request): ?string
    {
        // Extract country code from Cloudflare's CF-IPCountry header
        $cfCountry = $request->headers->get(self::CF_COUNTRY_HEADER);

        // Validate CF country code: must be exactly 2 uppercase letters (ISO 3166-1 alpha-2)
        if ($cfCountry && preg_match('/^[A-Z]{2}$/', $cfCountry)) {
            return strtoupper($cfCountry);
        }

        // Fallback to GeoIP database lookup if CF header is missing or invalid
        try {
            // Get the client IP address for database lookup
            $ip = $this->getIp($request);
        } catch (\Exception $e) {
            // Re-throw with more descriptive message if IP detection fails
            throw new Exception("Unable to determine client IP address.");
        }

        // Initialize GeoIP2 reader with the configured database
        $reader = new Reader($this->databasePath);

        // Perform country lookup and extract ISO code
        $country = $reader->country($ip)->country;
        return strtoupper($country->isoCode);
    }
}