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

### Sidecar Pattern with Docker Compose

For production deployments, you can use the AppConfig Agent as a sidecar container. This approach provides better isolation, scalability, and resource management.

#### Basic Sidecar Configuration

```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8000:8000"
    environment:
      - APP_CONFIG_AGENT_HOST=agent
      - APP_CONFIG_AGENT_PORT=2772
    depends_on:
      agent:
        condition: service_healthy
    networks:
      - app-network

  agent:
    image: public.ecr.aws/aws-appconfig/aws-appconfig-agent:latest
    environment:
      - AWS_REGION=us-east-1
      - AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID}
      - AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY}
      - AWS_SESSION_TOKEN=${AWS_SESSION_TOKEN}
      - AWS_APPCONFIG_APPLICATION=my-app
      - AWS_APPCONFIG_ENVIRONMENT=production
      - AWS_APPCONFIG_CONFIGURATION_PROFILE=feature-flags
    volumes:
      - ./configs:/opt/appconfig-agent/configs
    ports:
      - "2772:2772"
    networks:
      - app-network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:2772/health"]
      interval: 30s
      timeout: 10s
      retries: 3
    restart: unless-stopped

networks:
  app-network:
    driver: bridge
```

#### Application Configuration for Sidecar

```php
use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Configuration\ConfigurationSourceType;

$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1',
    sourceType: ConfigurationSourceType::AGENT,
    agentHost: $_ENV['APP_CONFIG_AGENT_HOST'] ?? 'localhost',
    agentPort: (int)($_ENV['APP_CONFIG_AGENT_PORT'] ?? 2772),
    enablePolling: true,
    pollingInterval: 60
);
```

#### Kubernetes Sidecar Configuration

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: my-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: my-app
  template:
    metadata:
      labels:
        app: my-app
    spec:
      containers:
      - name: app
        image: my-app:latest
        ports:
        - containerPort: 8000
        env:
        - name: APP_CONFIG_AGENT_HOST
          value: "localhost"
        - name: APP_CONFIG_AGENT_PORT
          value: "2772"
        resources:
          requests:
            memory: "128Mi"
            cpu: "100m"
          limits:
            memory: "256Mi"
            cpu: "200m"

      - name: appconfig-agent
        image: public.ecr.aws/aws-appconfig/aws-appconfig-agent:latest
        ports:
        - containerPort: 2772
        env:
        - name: AWS_REGION
          value: "us-east-1"
        - name: AWS_APPCONFIG_APPLICATION
          value: "my-app"
        - name: AWS_APPCONFIG_ENVIRONMENT
          value: "production"
        - name: AWS_APPCONFIG_CONFIGURATION_PROFILE
          value: "feature-flags"
        - name: AWS_ACCESS_KEY_ID
          valueFrom:
            secretKeyRef:
              name: aws-credentials
              key: access-key-id
        - name: AWS_SECRET_ACCESS_KEY
          valueFrom:
            secretKeyRef:
              name: aws-credentials
              key: secret-access-key
        resources:
          requests:
            memory: "64Mi"
            cpu: "50m"
          limits:
            memory: "128Mi"
            cpu: "100m"
        livenessProbe:
          httpGet:
            path: /health
            port: 2772
          initialDelaySeconds: 30
          periodSeconds: 30
        readinessProbe:
          httpGet:
            path: /health
            port: 2772
          initialDelaySeconds: 5
          periodSeconds: 10
```

#### Benefits of Sidecar Pattern

- **Isolation**: Agent runs in its own container with dedicated resources
- **Scalability**: Each application instance has its own agent
- **Reliability**: Agent failures don't affect the main application
- **Monitoring**: Independent health checks and metrics
- **Security**: Network isolation and resource limits
- **Updates**: Independent deployment and versioning

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

# Run unit tests only
composer test-unit

# Run integration tests only
composer test-integration

# Run specific test file
./vendor/bin/phpunit tests/Unit/AwsAppConfigProviderTest.php
```

#### Integration Testing with Docker

The project includes integration tests that can run against a real AppConfig Agent using Docker:

```bash
# Start AppConfig Agent (Docker required)
composer agent:start

# Run integration tests with agent
composer test-integration

# Stop AppConfig Agent
composer agent:stop

# Or run all integration tests with agent automatically
composer test:with-agent
```

The integration tests will automatically detect if the Docker agent is available and use it for testing. If the agent is not available, the tests will fall back to using mock data.

#### Development with Sidecar Agent

For local development, you can use the provided Docker Compose configuration:

```bash
# Start the application with sidecar agent
docker-compose -f docker-compose.agent.yml up -d

# Run your application
docker-compose up app

# Stop all services
docker-compose -f docker-compose.agent.yml down
```

The `docker-compose.agent.yml` file provides a complete development environment with:
- AppConfig Agent running on port 2772
- Health checks and automatic restarts
- Local configuration for testing
- Network isolation for security

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

## Troubleshooting

### Common Issues with Sidecar Agent

#### Agent Connection Issues
```bash
# Check if agent is running
curl http://localhost:2772/health

# Check agent logs
docker logs appconfig-agent-test

# Verify network connectivity
docker exec app ping agent
```

#### Configuration Sync Issues
- Ensure the agent has proper IAM permissions for AppConfig
- Check that the application, environment, and configuration profile exist
- Verify AWS credentials are correctly configured
- Check agent logs for detailed error messages

#### Performance Issues
- Monitor agent resource usage: `docker stats appconfig-agent-test`
- Adjust polling intervals based on your requirements
- Consider using caching to reduce API calls
- Monitor network latency between app and agent

### Debugging

Enable debug logging for the provider:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('openfeature');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

$config = new Configuration(
    // ... other config
    logger: $logger
);
```

## Support

- [OpenFeature Documentation](https://openfeature.dev/docs)
- [AWS AppConfig Documentation](https://docs.aws.amazon.com/appconfig/)
- [Issues](https://github.com/openfeature/php-aws-appconfig-provider/issues)
