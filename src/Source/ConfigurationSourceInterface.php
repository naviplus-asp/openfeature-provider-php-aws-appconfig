<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Source;

use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\interfaces\flags\EvaluationContext;

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
     * Evaluate a feature flag with context using the source's evaluation engine
     *
     * @param string $flagKey Feature flag key
     * @param Configuration $config Provider configuration
     * @param EvaluationContext $context Evaluation context
     * @param mixed $defaultValue Default value if flag is not found
     * @return mixed Evaluated value
     * @throws \OpenFeature\Providers\AwsAppConfig\Exception\AwsAppConfigException
     */
    public function evaluateFlag(string $flagKey, Configuration $config, EvaluationContext $context, mixed $defaultValue): mixed;

    /**
     * Check if this source supports local flag evaluation
     *
     * @return bool True if local evaluation is supported
     */
    public function supportsLocalEvaluation(): bool;

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
