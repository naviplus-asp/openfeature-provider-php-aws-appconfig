<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Evaluator;

use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\Providers\AwsAppConfig\Exception\AwsAppConfigException;

/**
 * Evaluates feature flags based on AWS AppConfig configuration
 */
class FeatureFlagEvaluator
{
    /**
     * Evaluate a boolean feature flag
     *
     * @param string $flagKey Feature flag key
     * @param array $configuration AppConfig configuration data
     * @param EvaluationContext $context Evaluation context
     * @param bool $defaultValue Default value if flag is not found
     * @return bool Evaluated boolean value
     * @throws AwsAppConfigException
     */
    public function evaluateBoolean(
        string $flagKey,
        array $configuration,
        EvaluationContext $context,
        bool $defaultValue
    ): bool {
        $flag = $this->getFlag($flagKey, $configuration);

        if ($flag === null) {
            return $defaultValue;
        }

        return $this->evaluateFlag($flag, $context, $defaultValue);
    }

    /**
     * Evaluate a string feature flag
     *
     * @param string $flagKey Feature flag key
     * @param array $configuration AppConfig configuration data
     * @param EvaluationContext $context Evaluation context
     * @param string $defaultValue Default value if flag is not found
     * @return string Evaluated string value
     * @throws AwsAppConfigException
     */
    public function evaluateString(
        string $flagKey,
        array $configuration,
        EvaluationContext $context,
        string $defaultValue
    ): string {
        $flag = $this->getFlag($flagKey, $configuration);

        if ($flag === null) {
            return $defaultValue;
        }

        return $this->evaluateFlag($flag, $context, $defaultValue);
    }

    /**
     * Evaluate a number feature flag
     *
     * @param string $flagKey Feature flag key
     * @param array $configuration AppConfig configuration data
     * @param EvaluationContext $context Evaluation context
     * @param float|int $defaultValue Default value if flag is not found
     * @return float|int Evaluated number value
     * @throws AwsAppConfigException
     */
    public function evaluateNumber(
        string $flagKey,
        array $configuration,
        EvaluationContext $context,
        float|int $defaultValue
    ): float|int {
        $flag = $this->getFlag($flagKey, $configuration);

        if ($flag === null) {
            return $defaultValue;
        }

        return $this->evaluateFlag($flag, $context, $defaultValue);
    }

    /**
     * Evaluate an object feature flag
     *
     * @param string $flagKey Feature flag key
     * @param array $configuration AppConfig configuration data
     * @param EvaluationContext $context Evaluation context
     * @param array $defaultValue Default value if flag is not found
     * @return array Evaluated object value
     * @throws AwsAppConfigException
     */
    public function evaluateObject(
        string $flagKey,
        array $configuration,
        EvaluationContext $context,
        array $defaultValue
    ): array {
        $flag = $this->getFlag($flagKey, $configuration);

        if ($flag === null) {
            return $defaultValue;
        }

        return $this->evaluateFlag($flag, $context, $defaultValue);
    }

    /**
     * Get flag configuration from AppConfig data
     *
     * @param string $flagKey Feature flag key
     * @param array $configuration AppConfig configuration data
     * @return array|null Flag configuration or null if not found
     */
    private function getFlag(string $flagKey, array $configuration): ?array
    {
        $features = $configuration['features'] ?? [];

        return $features[$flagKey] ?? null;
    }

    /**
     * Evaluate flag based on rules and context
     *
     * @param array $flag Flag configuration
     * @param EvaluationContext $context Evaluation context
     * @param mixed $defaultValue Default value
     * @return mixed Evaluated value
     * @throws AwsAppConfigException
     */
    private function evaluateFlag(array $flag, EvaluationContext $context, mixed $defaultValue): mixed
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
}
