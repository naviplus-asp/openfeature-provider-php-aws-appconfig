<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Source;

use Aws\AppConfig\AppConfigClient;
use Aws\Exception\AwsException;
use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Exception\AwsAppConfigException;
use OpenFeature\Providers\AwsAppConfig\Exception\ConfigurationNotFoundException;

/**
 * AWS SDK-based configuration source
 */
class AwsSdkSource implements ConfigurationSourceInterface
{
    private AppConfigClient $appConfigClient;
    private ?string $configurationVersion = null;
    private ?int $lastModified = null;

    public function __construct(AppConfigClient $appConfigClient)
    {
        $this->appConfigClient = $appConfigClient;
    }

    public function loadConfiguration(Configuration $config): array
    {
        try {
            $result = $this->appConfigClient->getConfiguration([
                'Application' => $config->getApplication(),
                'Environment' => $config->getEnvironment(),
                'Configuration' => $config->getConfigurationProfile(),
            ]);

            $content = $result['Content'];
            $configuration = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new AwsAppConfigException('Invalid JSON configuration from AppConfig');
            }

            // Store metadata
            $this->configurationVersion = $result['ConfigurationVersion'] ?? null;
            $this->lastModified = $result['LastModified'] ?? null;

            return $configuration;
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                throw new ConfigurationNotFoundException(
                    $config->getApplication(),
                    $config->getEnvironment(),
                    $config->getConfigurationProfile(),
                    $e
                );
            }

            throw new AwsAppConfigException(
                'Failed to load configuration from AWS AppConfig: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function supportsPolling(): bool
    {
        return true;
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function getLastModified(): ?int
    {
        return $this->lastModified;
    }

    public function getConfigurationVersion(): ?string
    {
        return $this->configurationVersion;
    }

    public function isAvailable(Configuration $config): bool
    {
        try {
            // Try to get configuration metadata to check availability
            $this->appConfigClient->getConfiguration([
                'Application' => $config->getApplication(),
                'Environment' => $config->getEnvironment(),
                'Configuration' => $config->getConfigurationProfile(),
            ]);

            return true;
        } catch (AwsException $e) {
            // If it's a resource not found error, the source is available but the config doesn't exist
            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                return true;
            }

            return false;
        }
    }
}
