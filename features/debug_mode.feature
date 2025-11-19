Feature: Debug mode geolocation overrides
  In order to simulate Cloudflare behavior while working locally
  As a developer
  I want to force the service to return fake IPs and country codes through debug mode

  Scenario: Force responses via debug mode
    Given the request remote address is "198.51.100.1"
    And the request has header "CF-Connecting-IP" with value "8.8.8.8"
    And the request has header "CF-IPCountry" with value "US"
    When I set debug mode to enabled with IP "203.0.113.77" and country "pl"
    And I fetch the client IP
    And I fetch the country code
    Then the resolved IP should be "203.0.113.77"
    And the resolved country should be "PL"
    And debug mode should report enabled with IP "203.0.113.77" and country "PL"

  Scenario: Disabling debug mode restores Cloudflare detection
    Given the request has header "CF-Connecting-IP" with value "9.9.9.9"
    And the request has header "CF-IPCountry" with value "FR"
    And debug mode is enabled with IP "203.0.113.77" and country "DE"
    When I set debug mode to disabled
    And I fetch the client IP
    And I fetch the country code
    Then the resolved IP should be "9.9.9.9"
    And the resolved country should be "FR"
    And debug mode should report disabled
