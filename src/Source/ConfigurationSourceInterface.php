<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Source;

use OpenFeature\Providers\AwsAppConfig\Configuration;

/**
 * Interface for configuration sources (AWS SDK, AppConfig Agent, etc.)
 */
interface ConfigurationSourceInterface
{
    /**
     * Load configuration from the source
     *
     * @param Configuration $config Provider configuration
     * @return array Configuration data
     * @throws \OpenFeature\Providers\AwsAppConfig\Exception\AwsAppConfigException
     */
    public function loadConfiguration(Configuration $config): array;

    /**
     * Check if this source supports polling for updates
     *
     * @return bool True if polling is supported
     */
    public function supportsPolling(): bool;

    /**
     * Check if this source supports webhook-based updates
     *
     * @return bool True if webhooks are supported
     */
    public function supportsWebhooks(): bool;

    /**
     * Get the last modification time of the configuration
     *
     * @return int|null Unix timestamp of last modification, or null if not available
     */
    public function getLastModified(): ?int;

    /**
     * Get the configuration version
     *
     * @return string|null Configuration version, or null if not available
     */
    public function getConfigurationVersion(): ?string;

    /**
     * Check if the configuration source is available
     *
     * @param Configuration $config Provider configuration
     * @return bool True if the source is available
     */
    public function isAvailable(Configuration $config): bool;
}
