<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig;

use OpenFeature\Providers\AwsAppConfig\Configuration\ConfigurationSourceType;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Configuration class for AWS AppConfig Provider
 */
class Configuration
{
    /**
     * @param string $application AWS AppConfig application name
     * @param string $environment AWS AppConfig environment name
     * @param string $configurationProfile AWS AppConfig configuration profile name
     * @param string $region AWS region
     * @param ConfigurationSourceType $sourceType Configuration source type (default: AWS_SDK)
     * @param CacheItemPoolInterface|null $cache PSR-6 cache implementation
     * @param int $cacheTtl Cache TTL in seconds (default: 300)
     * @param int $pollingInterval Polling interval in seconds (default: 60)
     * @param int $maxRetries Maximum number of retries for AWS API calls (default: 3)
     * @param LoggerInterface|null $logger PSR-3 logger implementation
     * @param array $awsConfig Additional AWS SDK configuration
     * @param string|null $agentPath Path to AppConfig agent (for AGENT source type)
     * @param string|null $localConfigPath Path to local configuration file (for AGENT source type)
     * @param bool $enablePolling Enable background polling (default: false)
     * @param bool $enableWebhooks Enable webhook support (default: false)
     * @param string|null $webhookEndpoint Webhook endpoint URL (for webhook support)
     */
    public function __construct(
        private readonly string $application,
        private readonly string $environment,
        private readonly string $configurationProfile,
        private readonly string $region,
        private readonly ConfigurationSourceType $sourceType = ConfigurationSourceType::AWS_SDK,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly int $cacheTtl = 300,
        private readonly int $pollingInterval = 60,
        private readonly int $maxRetries = 3,
        private readonly ?LoggerInterface $logger = null,
        private readonly array $awsConfig = [],
        private readonly ?string $agentPath = null,
        private readonly ?string $localConfigPath = null,
        private readonly bool $enablePolling = false,
        private readonly bool $enableWebhooks = false,
        private readonly ?string $webhookEndpoint = null
    ) {
    }

    public function getApplication(): string
    {
        return $this->application;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getConfigurationProfile(): string
    {
        return $this->configurationProfile;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getCache(): ?CacheItemPoolInterface
    {
        return $this->cache;
    }

    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public function getPollingInterval(): int
    {
        return $this->pollingInterval;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function getAwsConfig(): array
    {
        return $this->awsConfig;
    }

    public function getSourceType(): ConfigurationSourceType
    {
        return $this->sourceType;
    }

    public function getAgentPath(): ?string
    {
        return $this->agentPath;
    }

    public function getLocalConfigPath(): ?string
    {
        return $this->localConfigPath;
    }

    public function isPollingEnabled(): bool
    {
        return $this->enablePolling;
    }

    public function isWebhooksEnabled(): bool
    {
        return $this->enableWebhooks;
    }

    public function getWebhookEndpoint(): ?string
    {
        return $this->webhookEndpoint;
    }
}
