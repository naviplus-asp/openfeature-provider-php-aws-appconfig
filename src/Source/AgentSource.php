<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Source;

use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Exception\AwsAppConfigException;
use OpenFeature\Providers\AwsAppConfig\Exception\ConfigurationNotFoundException;
use OpenFeature\interfaces\flags\EvaluationContext;

/**
 * AppConfig Agent-based configuration source
 */
class AgentSource implements ConfigurationSourceInterface
{
    private ?string $configurationVersion = null;
    private ?int $lastModified = null;
    private ?array $mockConfiguration = null;

    /**
     * Set mock configuration for testing
     */
    public function setMockConfiguration(?array $configuration): void
    {
        $this->mockConfiguration = $configuration;
    }

    public function loadConfiguration(Configuration $config): array
    {
        // Return mock configuration if set (for testing)
        if ($this->mockConfiguration !== null) {
            return $this->mockConfiguration;
        }

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

    public function evaluateFlag(
        string $flagKey,
        Configuration $config,
        EvaluationContext $context,
        mixed $defaultValue
    ): mixed {
        // Try to use AppConfig Agent's HTTP evaluation API if available
        if ($this->isAgentEvaluationAvailable($config)) {
            return $this->evaluateFlagWithAgent($flagKey, $config, $context, $defaultValue);
        }

        // Fallback to local evaluation
        return $this->evaluateFlagLocally($flagKey, $config, $context, $defaultValue);
    }

    public function supportsLocalEvaluation(): bool
    {
        return true;
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
     * Check if AppConfig Agent evaluation API is available
     *
     * @param Configuration $config Provider configuration
     * @return bool True if agent evaluation is available
     */
    private function isAgentEvaluationAvailable(Configuration $config): bool
    {
        $agentHost = $config->getAgentHost() ?? 'localhost';
        $agentPort = $config->getAgentPort() ?? 2772;
        $evaluationEndpoint = "http://{$agentHost}:{$agentPort}/evaluate";

        // Try to connect to the agent's HTTP endpoint
        $context = stream_context_create([
            'http' => [
                'timeout' => 1,
                'method' => 'HEAD'
            ]
        ]);

        $result = @file_get_contents($evaluationEndpoint, false, $context);
        return $result !== false;
    }

    /**
     * Evaluate flag using AppConfig Agent's HTTP API
     *
     * @param string $flagKey Feature flag key
     * @param Configuration $config Provider configuration
     * @param EvaluationContext $context Evaluation context
     * @param mixed $defaultValue Default value
     * @return mixed Evaluated value
     * @throws AwsAppConfigException
     */
    private function evaluateFlagWithAgent(
        string $flagKey,
        Configuration $config,
        EvaluationContext $context,
        mixed $defaultValue
    ): mixed {
        $agentHost = $config->getAgentHost() ?? 'localhost';
        $agentPort = $config->getAgentPort() ?? 2772;
        $evaluationEndpoint = "http://{$agentHost}:{$agentPort}/evaluate";

        // Prepare evaluation request
        $request = [
            'flagKey' => $flagKey,
            'application' => $config->getApplication(),
            'environment' => $config->getEnvironment(),
            'configurationProfile' => $config->getConfigurationProfile(),
            'context' => $context->getAttributes()->toArray(),
            'defaultValue' => $defaultValue,
        ];

        try {
            // Prepare HTTP request
            $httpContext = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ],
                    'content' => json_encode($request),
                    'timeout' => 5
                ]
            ]);

            // Send HTTP request to agent
            $response = file_get_contents($evaluationEndpoint, false, $httpContext);

            if ($response === false) {
                throw new AwsAppConfigException('Failed to connect to AppConfig Agent');
            }

            // Parse response
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new AwsAppConfigException('Invalid JSON response from agent evaluation');
            }

            return $result['value'] ?? $defaultValue;
        } catch (\Exception $e) {
            if ($e instanceof AwsAppConfigException) {
                throw $e;
            }

            throw new AwsAppConfigException(
                'Failed to evaluate flag with agent: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Evaluate flag using local evaluation (fallback)
     *
     * @param string $flagKey Feature flag key
     * @param Configuration $config Provider configuration
     * @param EvaluationContext $context Evaluation context
     * @param mixed $defaultValue Default value
     * @return mixed Evaluated value
     * @throws AwsAppConfigException
     */
    private function evaluateFlagLocally(
        string $flagKey,
        Configuration $config,
        EvaluationContext $context,
        mixed $defaultValue
    ): mixed {
        $configuration = $this->loadConfiguration($config);
        $flags = $configuration['flags'] ?? [];
        $flag = $flags[$flagKey] ?? null;

        if ($flag === null) {
            return $defaultValue;
        }

        // Use local evaluation logic (simplified implementation)
        return $this->evaluateFlagValue($flag, $context, $defaultValue);
    }

    /**
     * Evaluate flag value based on rules and context
     *
     * @param array $flag Flag configuration
     * @param EvaluationContext $context Evaluation context
     * @param mixed $defaultValue Default value
     * @return mixed Evaluated value
     */
    private function evaluateFlagValue(array $flag, EvaluationContext $context, mixed $defaultValue): mixed
    {
        // Check if flag is enabled
        if (($flag['state'] ?? 'DISABLED') !== 'ENABLED') {
            return $defaultValue;
        }

        // Get default variants
        $defaultVariants = $flag['defaultVariants'] ?? [];

        // Check if flag has targeting rules
        $targeting = $flag['targeting'] ?? null;
        $rules = $targeting['rules'] ?? [];

        if (empty($rules)) {
            // Return first available default variant or defaultValue
            return $this->getFirstVariant($defaultVariants) ?? $defaultValue;
        }

        // Evaluate rules in order
        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $context)) {
                $variants = $rule['variants'] ?? [];
                return $this->getFirstVariant($variants) ?? $defaultValue;
            }
        }

        // Return default value if no rules match
        return $this->getFirstVariant($defaultVariants) ?? $defaultValue;
    }

    /**
     * Evaluate a single rule
     *
     * @param array $rule Rule configuration
     * @param EvaluationContext $context Evaluation context
     * @return bool True if rule matches, false otherwise
     */
    private function evaluateRule(array $rule, EvaluationContext $context): bool
    {
        $condition = $rule['condition'] ?? null;

        if ($condition === null) {
            return true; // No condition means always match
        }

        // Simple condition evaluation (can be extended for more complex expressions)
        return $this->evaluateCondition($condition, $context);
    }

    /**
     * Evaluate a condition expression
     *
     * @param string $condition Condition expression
     * @param EvaluationContext $context Evaluation context
     * @return bool True if condition is met, false otherwise
     */
    private function evaluateCondition(string $condition, EvaluationContext $context): bool
    {
        // Simple condition evaluation - can be extended with a proper expression parser
        // For now, we'll implement basic equality checks

        if (preg_match('/^([^=]+)\s*==\s*["\']([^"\']*)["\']$/', $condition, $matches)) {
            $path = trim($matches[1]);
            $expectedValue = $matches[2];

            $actualValue = $this->getValueFromContext($path, $context);

            return $actualValue === $expectedValue;
        }

        // Default to false for unrecognized conditions
        return false;
    }

    /**
     * Get first available variant from variants array
     *
     * @param array $variants Variants array
     * @return mixed First variant value or null
     */
    private function getFirstVariant(array $variants): mixed
    {
        if (empty($variants)) {
            return null;
        }

        // Return first available variant value
        foreach ($variants as $type => $value) {
            return $value;
        }

        // This should never be reached, but PHPStan requires it
        return null;
    }

    /**
     * Get value from context using dot notation
     *
     * @param string $path Dot notation path (e.g., "user.id")
     * @param EvaluationContext $context Evaluation context
     * @return mixed Value at path or null if not found
     */
    private function getValueFromContext(string $path, EvaluationContext $context): mixed
    {
        $keys = explode('.', $path);
        $attributes = $context->getAttributes();
        $value = $attributes->toArray();

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
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
