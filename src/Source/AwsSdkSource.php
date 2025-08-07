<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Source;

use Aws\AppConfig\AppConfigClient;
use Aws\Exception\AwsException;
use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Exception\AwsAppConfigException;
use OpenFeature\Providers\AwsAppConfig\Exception\ConfigurationNotFoundException;
use OpenFeature\interfaces\flags\EvaluationContext;

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

    public function evaluateFlag(
        string $flagKey,
        Configuration $config,
        EvaluationContext $context,
        mixed $defaultValue
    ): mixed {
        // AWS SDK source doesn't support local evaluation
        // Load configuration and use local evaluation logic
        $configuration = $this->loadConfiguration($config);
        $features = $configuration['features'] ?? [];
        $flag = $features[$flagKey] ?? null;

        if ($flag === null) {
            return $defaultValue;
        }

        // Use simplified evaluation logic
        return $this->evaluateFlagValue($flag, $context, $defaultValue);
    }

    public function supportsLocalEvaluation(): bool
    {
        return false;
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
        // Check if flag has rules
        $rules = $flag['rules'] ?? [];

        if (empty($rules)) {
            return $flag['default'] ?? $defaultValue;
        }

        // Evaluate rules in order
        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $context)) {
                return $rule['value'] ?? $defaultValue;
            }
        }

        // Return default value if no rules match
        return $flag['default'] ?? $defaultValue;
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

        if (preg_match('/^(\w+(?:\.\w+)*)\s*==\s*["\']([^"\']*)["\']$/', $condition, $matches)) {
            $path = $matches[1];
            $expectedValue = $matches[2];

            $actualValue = $this->getValueFromContext($path, $context);

            return $actualValue === $expectedValue;
        }

        // Default to false for unrecognized conditions
        return false;
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
