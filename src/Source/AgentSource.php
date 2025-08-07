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

    public function evaluateFlag(string $flagKey, Configuration $config, EvaluationContext $context, mixed $defaultValue): mixed
    {
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
    private function evaluateFlagWithAgent(string $flagKey, Configuration $config, EvaluationContext $context, mixed $defaultValue): mixed
    {
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
    private function evaluateFlagLocally(string $flagKey, Configuration $config, EvaluationContext $context, mixed $defaultValue): mixed
    {
        $configuration = $this->loadConfiguration($config);
        $features = $configuration['features'] ?? [];
        $flag = $features[$flagKey] ?? null;

        if ($flag === null) {
            return $defaultValue;
        }

        // Use local evaluation logic (existing FeatureFlagEvaluator logic)
        return $this->evaluateFlagLocally($flag, $context, $defaultValue);
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
