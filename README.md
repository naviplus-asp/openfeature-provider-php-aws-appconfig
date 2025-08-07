# OpenFeature PHP AWS AppConfig Provider

[![PHP Version](https://img.shields.io/badge/php-8.1%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![OpenFeature](https://img.shields.io/badge/OpenFeature-Provider-green.svg)](https://openfeature.dev)

An OpenFeature provider for PHP that uses AWS AppConfig as the backend configuration store for feature flags.

## Features

- ✅ Full OpenFeature specification compliance
- ✅ AWS AppConfig integration
- ✅ Support for all evaluation types (boolean, string, number, object)
- ✅ Configurable caching with TTL
- ✅ Comprehensive error handling and fallbacks
- ✅ PSR-3 logging support
- ✅ PSR-6 caching interface support
- ✅ High test coverage (>90%)

## Installation

```bash
composer require openfeature/php-aws-appconfig-provider
```

## Quick Start

```php
<?php

use OpenFeature\OpenFeatureAPI;
use OpenFeature\Providers\AwsAppConfig\AwsAppConfigProvider;
use OpenFeature\Providers\AwsAppConfig\Configuration;

// Configure the provider
$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1'
);

// Create the provider
$provider = new AwsAppConfigProvider($config);

// Register with OpenFeature
$api = OpenFeatureAPI::getInstance();
$api->setProvider($provider);

// Use feature flags
$client = $api->getClient();
$isEnabled = $client->getBooleanValue('my-feature', false);
```

## Configuration

### Basic Configuration (AWS SDK)

```php
use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Configuration\ConfigurationSourceType;

$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1'
    // Defaults to AWS_SDK source type
);
```

### AppConfig Agent Configuration

```php
use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Configuration\ConfigurationSourceType;

$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1',
    sourceType: ConfigurationSourceType::AGENT,
    agentPath: '/opt/appconfig-agent',
    localConfigPath: '/var/appconfig/config.json',
    enablePolling: true,
    pollingInterval: 60,
    enableWebhooks: true,
    webhookEndpoint: '/webhook/appconfig'
);
```

### Advanced Configuration with Caching

```php
use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Cache\Psr6Cache;
use Psr\Cache\CacheItemPoolInterface;

$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1',
    sourceType: ConfigurationSourceType::AWS_SDK,
    cache: new Psr6Cache($cachePool),
    cacheTtl: 300, // 5 minutes
    pollingInterval: 60, // 1 minute
    maxRetries: 3
);
```

## Configuration Sources

This provider supports multiple configuration sources:

### 1. AWS SDK (Default)
- Direct API calls to AWS AppConfig
- Suitable for development and testing
- Higher latency but simpler setup

### 2. AppConfig Agent
- Local agent for improved performance
- Lower latency and reduced API costs
- Requires AppConfig agent installation

### 3. Hybrid (Future)
- Combined approach with fallback mechanisms
- Best of both worlds

## AWS AppConfig Setup

### For AWS SDK Source
1. Create an AppConfig application
2. Create an environment
3. Create a configuration profile
4. Configure feature flags in the profile
5. Deploy the configuration

### For AppConfig Agent Source
1. Install AppConfig agent on your server
2. Configure the agent with your AppConfig details
3. Ensure the agent has proper IAM permissions
4. The agent will automatically sync configuration to local files

### Example AppConfig Configuration

```json
{
  "features": {
    "my-feature": {
      "default": false,
      "rules": [
        {
          "condition": "user.id == 'admin'",
          "value": true
        }
      ]
    }
  }
}
```

## Usage Examples

### Boolean Flags

```php
$isEnabled = $client->getBooleanValue('my-feature', false);
$details = $client->getBooleanDetails('my-feature', false);
```

### String Flags

```php
$value = $client->getStringValue('welcome-message', 'Hello World');
$details = $client->getStringDetails('welcome-message', 'Hello World');
```

### Number Flags

```php
$value = $client->getNumberValue('discount-percentage', 0);
$details = $client->getNumberDetails('discount-percentage', 0);
```

### Object Flags

```php
$value = $client->getObjectValue('user-preferences', ['theme' => 'light']);
$details = $client->getObjectDetails('user-preferences', ['theme' => 'light']);
```

### With Context

```php
$context = new EvaluationContext([
    'user' => [
        'id' => 'user-123',
        'email' => 'user@example.com',
        'role' => 'admin'
    ]
]);

$isEnabled = $client->getBooleanValue('admin-feature', false, $context);
```

## Error Handling

The provider includes comprehensive error handling:

```php
try {
    $value = $client->getBooleanValue('my-feature', false);
} catch (ProviderException $e) {
    // Handle provider-specific errors
    error_log("Feature flag error: " . $e->getMessage());
    // Use default value
    $value = false;
}
```

## Caching

The provider supports PSR-6 caching:

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$cachePool = new FilesystemAdapter();
$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1',
    cache: new Psr6Cache($cachePool),
    cacheTtl: 300
);
```

## Logging

The provider supports PSR-3 logging:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('openfeature');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1',
    logger: $logger
);
```

## Development

### Prerequisites

- PHP 8.1+
- Composer
- Docker (optional)

### Setup

```bash
# Clone the repository
git clone https://github.com/openfeature/php-aws-appconfig-provider.git
cd php-aws-appconfig-provider

# Install dependencies
composer install

# Run tests
composer test

# Run code quality checks
composer check
```

### Testing

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test file
./vendor/bin/phpunit tests/Unit/AwsAppConfigProviderTest.php
```

### Code Quality

```bash
# Run static analysis
composer phpstan

# Run code style checks
composer phpcs

# Fix code style issues
composer phpcbf
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- [OpenFeature Documentation](https://openfeature.dev/docs)
- [AWS AppConfig Documentation](https://docs.aws.amazon.com/appconfig/)
- [Issues](https://github.com/openfeature/php-aws-appconfig-provider/issues)
