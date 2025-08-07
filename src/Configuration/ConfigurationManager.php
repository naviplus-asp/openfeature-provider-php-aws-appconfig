<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Configuration;

use OpenFeature\Providers\AwsAppConfig\Cache\CacheInterface;
use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Exception\AwsAppConfigException;
use OpenFeature\Providers\AwsAppConfig\Source\ConfigurationSourceInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages configuration loading and caching
 */
class ConfigurationManager
{
    private array $configuration = [];
    private int $lastFetchTime = 0;
    private ?string $configurationVersion = null;
    private ?int $lastModified = null;

    public function __construct(
        private readonly ConfigurationSourceInterface $source,
        private readonly Configuration $config,
        private readonly ?CacheInterface $cache = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Get the current configuration, loading if necessary
     *
     * @return array Configuration data
     * @throws AwsAppConfigException
     */
    public function getConfiguration(): array
    {
        if ($this->shouldRefreshConfiguration()) {
            $this->refreshConfiguration();
        }

        return $this->configuration;
    }

    /**
     * Get the configuration source
     *
     * @return ConfigurationSourceInterface Configuration source
     */
    public function getSource(): ConfigurationSourceInterface
    {
        return $this->source;
    }

    /**
     * Get the configuration object
     *
     * @return Configuration Configuration object
     */
    public function getConfig(): Configuration
    {
        return $this->config;
    }

    /**
     * Refresh the configuration from the source
     *
     * @throws AwsAppConfigException
     */
    public function refreshConfiguration(): void
    {
        try {
            $cacheKey = $this->getCacheKey();

            // Try to get from cache first
            if ($this->cache !== null) {
                $cached = $this->cache->get($cacheKey);
                if ($cached !== null && !$this->isConfigurationStale($cached)) {
                    $this->configuration = $cached['data'];
                    $this->lastFetchTime = $cached['timestamp'];
                    $this->configurationVersion = $cached['version'] ?? null;
                    $this->lastModified = $cached['lastModified'] ?? null;

                    $this->logInfo('Configuration loaded from cache', [
                        'cacheKey' => $cacheKey,
                        'version' => $this->configurationVersion,
                    ]);

                    return;
                }
            }

            // Load from source
            $this->configuration = $this->source->loadConfiguration($this->config);
            $this->lastFetchTime = time();
            $this->configurationVersion = $this->source->getConfigurationVersion();
            $this->lastModified = $this->source->getLastModified();

            // Cache the configuration
            if ($this->cache !== null) {
                $cacheData = [
                    'data' => $this->configuration,
                    'timestamp' => $this->lastFetchTime,
                    'version' => $this->configurationVersion,
                    'lastModified' => $this->lastModified,
                ];

                $this->cache->set($cacheKey, $cacheData, $this->config->getCacheTtl());
            }

            $this->logInfo('Configuration loaded from source', [
                'source' => $this->config->getSourceType()->value,
                'version' => $this->configurationVersion,
            ]);
        } catch (\Exception $e) {
            $this->logError('Failed to refresh configuration', [
                'error' => $e->getMessage(),
                'source' => $this->config->getSourceType()->value,
            ]);

            throw new AwsAppConfigException(
                'Failed to refresh configuration: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check if configuration should be refreshed
     *
     * @return bool True if configuration should be refreshed
     */
    public function shouldRefreshConfiguration(): bool
    {
        // If no configuration is loaded, refresh
        if (empty($this->configuration)) {
            return true;
        }

        // If polling is enabled and configuration is stale, refresh
        if ($this->config->isPollingEnabled() && $this->isConfigurationStale()) {
            return true;
        }

        return false;
    }

    /**
     * Check if the current configuration is stale
     *
     * @param array|null $cachedData Cached data to check (optional)
     * @return bool True if configuration is stale
     */
    public function isConfigurationStale(?array $cachedData = null): bool
    {
        $lastFetch = $cachedData['timestamp'] ?? $this->lastFetchTime;
        $cacheTtl = $this->config->getCacheTtl();

        return (time() - $lastFetch) > $cacheTtl;
    }

    /**
     * Get the last fetch time
     *
     * @return int Unix timestamp
     */
    public function getLastFetchTime(): int
    {
        return $this->lastFetchTime;
    }

    /**
     * Get the configuration version
     *
     * @return string|null Configuration version
     */
    public function getConfigurationVersion(): ?string
    {
        return $this->configurationVersion;
    }

    /**
     * Get the last modification time
     *
     * @return int|null Unix timestamp
     */
    public function getLastModified(): ?int
    {
        return $this->lastModified;
    }

    /**
     * Clear the cached configuration
     */
    public function clearCache(): void
    {
        if ($this->cache !== null) {
            $this->cache->delete($this->getCacheKey());
        }

        $this->configuration = [];
        $this->lastFetchTime = 0;
        $this->configurationVersion = null;
        $this->lastModified = null;

        $this->logInfo('Configuration cache cleared');
    }

    /**
     * Generate cache key for configuration
     *
     * @return string Cache key
     */
    private function getCacheKey(): string
    {
        return sprintf(
            'appconfig:%s:%s:%s:%s',
            $this->config->getSourceType()->value,
            $this->config->getApplication(),
            $this->config->getEnvironment(),
            $this->config->getConfigurationProfile()
        );
    }

    /**
     * Log info message
     *
     * @param string $message Log message
     * @param array $context Log context
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array $context Log context
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }
}
