# CFGeolocationService

[![CI Tests](https://github.com/GryfOSS/http-cf-geolocation-service/actions/workflows/ci.yml/badge.svg)](https://github.com/GryfOSS/http-cf-geolocation-service/actions/workflows/ci.yml)
[![Quick Tests](https://github.com/GryfOSS/http-cf-geolocation-service/actions/workflows/quick-test.yml/badge.svg)](https://github.com/GryfOSS/http-cf-geolocation-service/actions/workflows/quick-test.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A high-performance PHP service for detecting user geolocation using Cloudflare headers with MaxMind GeoIP database fallback.

## 🎯 Purpose

**CFGeolocationService** is designed to provide fast and accurate geolocation detection by leveraging Cloudflare's edge network capabilities while maintaining reliability through GeoIP database fallbacks. This dual-strategy approach ensures optimal performance and accuracy for web applications behind Cloudflare's proxy network.

### Key Benefits

- **🚀 Performance**: Prioritizes Cloudflare headers for instant geolocation (no database lookup required)
- **🛡️ Reliability**: Falls back to MaxMind GeoIP2 database when Cloudflare headers are unavailable
- **🌐 Accuracy**: Uses Cloudflare's global edge network data combined with MaxMind's proven database
- **⚡ Speed**: Minimal overhead with intelligent caching strategy
- **🔒 Security**: Validates all inputs and handles edge cases gracefully

## 🏗️ How It Works

```
1. Check CF-IPCountry header → Valid ISO country code? → Return country
                                      ↓
2. Check CF-Connecting-IP header → Valid IP address? → Use for GeoIP lookup
                                      ↓
3. Fall back to Symfony getClientIp() → Perform GeoIP database lookup → Return country
```

### Dual Detection Strategy

1. **Primary**: Cloudflare Headers (instant results)
   - `CF-IPCountry`: ISO 3166-1 alpha-2 country code
   - `CF-Connecting-IP`: Real client IP address

2. **Fallback**: GeoIP Database Lookup
   - MaxMind GeoLite2/GeoIP2 Country database
   - Handles cases where CF headers are missing or invalid

## 🚀 Installation

### Via Composer

```bash
composer require gryfoss/http-cf-geolocation-service
```

### Requirements

- **PHP**: 8.1 or higher
- **Extensions**: mbstring, filter
- **Dependencies**:
  - `symfony/http-foundation` ^7.3
  - `geoip2/geoip2` ^3.2

## 📖 Usage

### Basic Usage

```php
<?php

use GryfOSS\Geolocation\CFGeolocationService;
use Symfony\Component\HttpFoundation\Request;

// Initialize with GeoIP database path
$service = new CFGeolocationService('/path/to/GeoLite2-Country.mmdb');

// Get current request
$request = Request::createFromGlobals();

// Detect client IP address
$clientIp = $service->getIp($request);
echo "Client IP: " . $clientIp; // e.g., "203.0.113.1"

// Detect country code
$countryCode = $service->getCountryCode($request);
echo "Country: " . $countryCode; // e.g., "US"
```

### With Cloudflare Headers

When your application is behind Cloudflare, the service automatically uses CF headers:

```php
// Cloudflare provides these headers:
// CF-Connecting-IP: 203.0.113.1
// CF-IPCountry: US

$countryCode = $service->getCountryCode($request);
// Returns "US" instantly (no database lookup needed)

$clientIp = $service->getIp($request);
// Returns "203.0.113.1" (real client IP, not Cloudflare's)
```

### Without Cloudflare Headers

The service gracefully falls back to GeoIP database lookup:

```php
// No CF headers available
$countryCode = $service->getCountryCode($request);
// Performs GeoIP database lookup on client IP
// Returns country code based on IP geolocation
```

### Error Handling

```php
use GryfOSS\Geolocation\CFGeolocationService;

try {
    $service = new CFGeolocationService('/invalid/path/database.mmdb');
} catch (\InvalidArgumentException $e) {
    echo "Database not found: " . $e->getMessage();
}

try {
    $countryCode = $service->getCountryCode($request);
} catch (\Exception $e) {
    echo "Geolocation failed: " . $e->getMessage();
}
```

### Complete Examples

See [`examples.php`](examples.php) for comprehensive usage examples including:

- Basic geolocation detection
- Cloudflare header simulation
- GeoIP database fallback scenarios
- Error handling demonstrations
- Testing with various IP addresses

```bash
# Run examples (requires database download first)
./pull-free-db.sh
php examples.php
```

## 🧪 Testing

### Download GeoIP Database

**⚠️ Important**: Due to MaxMind's licensing restrictions, the GeoIP database cannot be bundled with this package. You must download it separately.

```bash
# Download free GeoLite2 database
./pull-free-db.sh

# Or manually download from:
# https://dev.maxmind.com/geoip/geoip2/geolite2/
```

### Run Tests

```bash
# Install development dependencies
composer install

# Download test database
./pull-free-db.sh

# Run complete test suite
./vendor/bin/phpunit

# Run tests with coverage
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-text

# Run tests with readable output
./vendor/bin/phpunit --testdox

# Run usage examples
php examples.php
```

### Test Coverage

The project maintains **100% code coverage**:

- ✅ **Lines**: 100%
- ✅ **Methods**: 100%
- ✅ **Classes**: 100%

```bash
# Verify coverage locally
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage-report
# Open coverage-report/index.html in your browser
```

### Testing Strategy

- **Unit Tests**: 32 unit tests covering all methods and edge cases
- **Integration Tests**: 6 integration tests with real GeoIP database
- **Total**: 38 tests with 47 assertions
- **Data Providers**: Multiple test scenarios for various input combinations
- **Exception Testing**: Error handling and edge case validation
- **Real Database**: Tests use actual GeoLite2-Country.mmdb for authenticity

## 🗃️ GeoIP Database Setup

### Database Licensing Notice

**⚠️ Important Licensing Information**

This package **does not include** the MaxMind GeoIP database due to licensing restrictions:

- **GeoLite2 databases** are available for free but require separate download
- **Commercial GeoIP2 databases** require a MaxMind license
- **Distribution restrictions** prevent bundling databases with open-source packages

### Download Options

#### Option 1: Free GeoLite2 Database

```bash
# Use the included script
./pull-free-db.sh

# Manual download
wget https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-Country.mmdb
```

#### Option 2: Official MaxMind Account

1. Create account at [MaxMind](https://www.maxmind.com/en/accounts/current/license-key)
2. Generate license key
3. Download GeoLite2 or purchase GeoIP2 databases
4. Follow MaxMind's terms of service

#### Option 3: Automated Updates

```bash
# Set up cron job for monthly updates
0 0 1 * * /path/to/your/project/pull-free-db.sh
```

### Database Paths

```php
// Common database locations
$service = new CFGeolocationService('/usr/local/share/GeoIP/GeoLite2-Country.mmdb');
$service = new CFGeolocationService('/var/lib/GeoIP/GeoLite2-Country.mmdb');
$service = new CFGeolocationService('./data/GeoLite2-Country.mmdb');
```

## 🔧 Configuration

### Environment Variables

```bash
# Set database path via environment
export GEOIP_DATABASE_PATH="/path/to/GeoLite2-Country.mmdb"
```

```php
// Use in your application
$databasePath = $_ENV['GEOIP_DATABASE_PATH'] ?? '/default/path/GeoLite2-Country.mmdb';
$service = new CFGeolocationService($databasePath);
```

### Framework Integration

#### Symfony

```yaml
# config/services.yaml
services:
    GryfOSS\Geolocation\CFGeolocationService:
        arguments:
            $databasePath: '%env(GEOIP_DATABASE_PATH)%'
```

#### Laravel

```php
// config/app.php or service provider
$this->app->singleton(CFGeolocationService::class, function ($app) {
    return new CFGeolocationService(env('GEOIP_DATABASE_PATH'));
});
```

## 🏆 Quality Assurance

### GitHub Actions CI/CD

- **Multi-PHP Testing**: PHP 8.1, 8.2, 8.3, 8.4
- **Automated Database Download**: Fresh GeoLite2 database for each test run
- **100% Coverage Enforcement**: Builds fail if coverage drops below 100%
- **Cross-Platform**: Ubuntu latest with comprehensive test matrix

### Code Quality Standards

- **PSR-4 Autoloading**: Namespace compliance
- **PHPDoc Documentation**: Comprehensive method and class documentation
- **Type Declarations**: Strict typing for all parameters and returns
- **Error Handling**: Graceful exception handling for all failure modes
- **Input Validation**: Thorough validation of all external inputs

## 🤝 Contributing

We welcome contributions from the community! Here's how you can help:

### Reporting Issues

- **Bug Reports**: Use the [GitHub Issues](https://github.com/GryfOSS/http-cf-geolocation-service/issues) to report bugs
- **Feature Requests**: Propose new features or improvements
- **Security Issues**: Report security vulnerabilities privately

### Pull Requests

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/amazing-feature`
3. **Make your changes** with tests
4. **Ensure 100% test coverage**: `XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-text`
5. **Follow coding standards**: PSR-12 compliance
6. **Submit a pull request** with detailed description

### Development Guidelines

- **Write tests first**: TDD approach preferred
- **Maintain 100% coverage**: All new code must be fully tested
- **Follow existing patterns**: Consistent with current codebase
- **Document thoroughly**: PHPDoc for all public methods
- **Validate inputs**: Handle edge cases gracefully

### Code of Conduct

Please follow our code of conduct in all interactions:

- Be respectful and inclusive
- Focus on constructive feedback
- Help others learn and grow
- Maintain professionalism

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👥 Authors

- **IDCT Bartosz Pachołek** - [GitHub](https://github.com/bartosz-pachołek) - Initial work

## 🙏 Acknowledgments

- **MaxMind** for providing GeoIP2 and GeoLite2 databases
- **Cloudflare** for their excellent geolocation headers
- **Symfony** for the robust HTTP foundation component
- **P3TERX** for maintaining the GeoLite2 mirror repository
- **PHP Community** for continuous improvements and feedback

## 📚 Additional Resources

- **[Cloudflare Headers Documentation](https://developers.cloudflare.com/fundamentals/get-started/reference/http-request-headers/)**
- **[MaxMind GeoIP2 Documentation](https://dev.maxmind.com/geoip/docs/)**
- **[Symfony HTTP Foundation](https://symfony.com/doc/current/components/http_foundation.html)**

---

**Made with ❤️ by the GryfOSS team**