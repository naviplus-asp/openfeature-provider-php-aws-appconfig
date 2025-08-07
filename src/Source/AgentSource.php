<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Source;

use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Exception\AwsAppConfigException;
use OpenFeature\Providers\AwsAppConfig\Exception\ConfigurationNotFoundException;

/**
 * AppConfig Agent-based configuration source
 */
class AgentSource implements ConfigurationSourceInterface
{
    private ?string $configurationVersion = null;
    private ?int $lastModified = null;

    public function loadConfiguration(Configuration $config): array
    {
        $localConfigPath = $this->getLocalConfigPath($config);

        if (!file_exists($localConfigPath)) {
            throw new ConfigurationNotFoundException(
                $config->getApplication(),
                $config->getEnvironment(),
                $config->getConfigurationProfile(),
                null,
                "Local configuration file not found: {$localConfigPath}"
            );
        }

        try {
            $content = file_get_contents($localConfigPath);
            if ($content === false) {
                throw new AwsAppConfigException("Failed to read local configuration file: {$localConfigPath}");
            }

            $configuration = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new AwsAppConfigException('Invalid JSON configuration from local file');
            }

            // Try to read metadata from companion files
            $this->loadMetadata($localConfigPath);

            return $configuration;
        } catch (\Exception $e) {
            if ($e instanceof AwsAppConfigException) {
                throw $e;
            }

            throw new AwsAppConfigException(
                'Failed to load configuration from local file: ' . $e->getMessage(),
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
        return true;
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
        $localConfigPath = $this->getLocalConfigPath($config);
        return file_exists($localConfigPath) && is_readable($localConfigPath);
    }

    /**
     * Get the local configuration file path
     *
     * @param Configuration $config Provider configuration
     * @return string Local configuration file path
     */
    private function getLocalConfigPath(Configuration $config): string
    {
        // If a specific local config path is provided, use it
        if ($config->getLocalConfigPath() !== null) {
            return $config->getLocalConfigPath();
        }

        // Otherwise, construct the path based on AppConfig agent conventions
        $agentPath = $config->getAgentPath() ?? '/opt/appconfig-agent';
        $application = $config->getApplication();
        $environment = $config->getEnvironment();
        $profile = $config->getConfigurationProfile();

        return sprintf(
            '%s/configs/%s/%s/%s/config.json',
            $agentPath,
            $application,
            $environment,
            $profile
        );
    }

    /**
     * Load metadata from companion files
     *
     * @param string $configPath Configuration file path
     */
    private function loadMetadata(string $configPath): void
    {
        $basePath = dirname($configPath);

        // Try to read version from version file
        $versionFile = $basePath . '/version.txt';
        if (file_exists($versionFile)) {
            $version = file_get_contents($versionFile);
            if ($version !== false) {
                $this->configurationVersion = trim($version);
            }
        }

        // Try to read last modified from metadata file
        $metadataFile = $basePath . '/metadata.json';
        if (file_exists($metadataFile)) {
            $metadata = file_get_contents($metadataFile);
            if ($metadata !== false) {
                $metadataData = json_decode($metadata, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->lastModified = $metadataData['lastModified'] ?? null;
                    $this->configurationVersion = $metadataData['version'] ?? $this->configurationVersion;
                }
            }
        }

        // Fallback to file modification time
        if ($this->lastModified === null) {
            $fileTime = filemtime($configPath);
            $this->lastModified = $fileTime !== false ? $fileTime : null;
        }
    }
}
