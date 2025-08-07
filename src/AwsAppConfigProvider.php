<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig;

use Aws\AppConfig\AppConfigClient;
use Aws\Exception\AwsException;
use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\interfaces\provider\Provider;
use OpenFeature\interfaces\provider\ResolutionDetails;
use OpenFeature\implementation\provider\ResolutionDetails as ResolutionDetailsImpl;
use OpenFeature\implementation\provider\Reason;
use OpenFeature\implementation\flags\EvaluationContext as EvaluationContextImpl;
use OpenFeature\interfaces\hooks\HooksGetter;
use OpenFeature\interfaces\common\MetadataGetter;
use OpenFeature\interfaces\common\Metadata;
use OpenFeature\Providers\AwsAppConfig\Cache\CacheInterface;
use OpenFeature\Providers\AwsAppConfig\Cache\Psr6Cache;
use OpenFeature\Providers\AwsAppConfig\Configuration\ConfigurationManager;
use OpenFeature\Providers\AwsAppConfig\Evaluator\FeatureFlagEvaluator;
use OpenFeature\Providers\AwsAppConfig\Exception\AwsAppConfigException;
use OpenFeature\Providers\AwsAppConfig\Exception\ConfigurationNotFoundException;
use OpenFeature\Providers\AwsAppConfig\Source\AwsSdkSource;
use OpenFeature\Providers\AwsAppConfig\Source\AgentSource;
use OpenFeature\Providers\AwsAppConfig\Source\ConfigurationSourceInterface;
use OpenFeature\Providers\AwsAppConfig\Configuration\ConfigurationSourceType;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;

/**
 * AWS AppConfig Provider for OpenFeature
 */
class AwsAppConfigProvider implements Provider, HooksGetter, MetadataGetter, LoggerAwareInterface
{
    private FeatureFlagEvaluator $evaluator;
    private ConfigurationManager $configurationManager;
    private ?LoggerInterface $logger;

    public function __construct(Configuration $config)
    {
        $this->evaluator = new FeatureFlagEvaluator();
        $this->logger = $config->getLogger();

        // Initialize cache
        $cache = null;
        if ($config->getCache() instanceof CacheItemPoolInterface) {
            $cache = new Psr6Cache($config->getCache());
        }

        // Create configuration source based on source type
        $source = $this->createConfigurationSource($config);

        // Initialize configuration manager
        $this->configurationManager = new ConfigurationManager(
            $source,
            $config,
            $cache,
            $this->logger
        );
    }

    public function getMetadata(): Metadata
    {
        return new class implements Metadata {
            public function getName(): string
            {
                return 'aws-appconfig';
            }
        };
    }

    public function getHooks(): array
    {
        return [];
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function resolveBooleanValue(
        string $flagKey,
        bool $defaultValue,
        ?EvaluationContext $context = null
    ): ResolutionDetails {
        try {
            $context = $context ?? new EvaluationContextImpl();

            // Use source's evaluation if available, otherwise use local evaluator
            if ($this->configurationManager->getSource()->supportsLocalEvaluation()) {
                $value = $this->configurationManager->getSource()->evaluateFlag(
                    $flagKey,
                    $this->configurationManager->getConfig(),
                    $context,
                    $defaultValue
                );
            } else {
                $configuration = $this->configurationManager->getConfiguration();
                $value = $this->evaluator->evaluateBoolean(
                    $flagKey,
                    $configuration,
                    $context,
                    $defaultValue
                );
            }

            $details = new ResolutionDetailsImpl();
            $details->setValue($value);
            $details->setReason('TARGETING_MATCH');

            return $details;
        } catch (\Exception $e) {
            $this->logError('Boolean evaluation failed', [
                'flagKey' => $flagKey,
                'error' => $e->getMessage(),
            ]);

            $details = new ResolutionDetailsImpl();
            $details->setValue($defaultValue);
            $details->setReason(Reason::ERROR);

            return $details;
        }
    }

    public function resolveStringValue(
        string $flagKey,
        string $defaultValue,
        ?EvaluationContext $context = null
    ): ResolutionDetails {
        try {
            $context = $context ?? new EvaluationContextImpl();
            $configuration = $this->configurationManager->getConfiguration();

            $value = $this->evaluator->evaluateString(
                $flagKey,
                $configuration,
                $context,
                $defaultValue
            );

            $details = new ResolutionDetailsImpl();
            $details->setValue($value);
            $details->setReason('TARGETING_MATCH');

            return $details;
        } catch (\Exception $e) {
            $this->logError('String evaluation failed', [
                'flagKey' => $flagKey,
                'error' => $e->getMessage(),
            ]);

            $details = new ResolutionDetailsImpl();
            $details->setValue($defaultValue);
            $details->setReason(Reason::ERROR);

            return $details;
        }
    }

    public function resolveIntegerValue(
        string $flagKey,
        int $defaultValue,
        ?EvaluationContext $context = null
    ): ResolutionDetails {
        try {
            $context = $context ?? new EvaluationContextImpl();
            $configuration = $this->configurationManager->getConfiguration();

            $value = $this->evaluator->evaluateNumber(
                $flagKey,
                $configuration,
                $context,
                $defaultValue
            );

            $details = new ResolutionDetailsImpl();
            $details->setValue((int) $value);
            $details->setReason('TARGETING_MATCH');

            return $details;
        } catch (\Exception $e) {
            $this->logError('Integer evaluation failed', [
                'flagKey' => $flagKey,
                'error' => $e->getMessage(),
            ]);

            $details = new ResolutionDetailsImpl();
            $details->setValue($defaultValue);
            $details->setReason(Reason::ERROR);

            return $details;
        }
    }

    public function resolveFloatValue(
        string $flagKey,
        float $defaultValue,
        ?EvaluationContext $context = null
    ): ResolutionDetails {
        try {
            $context = $context ?? new EvaluationContextImpl();
            $configuration = $this->configurationManager->getConfiguration();

            $value = $this->evaluator->evaluateNumber(
                $flagKey,
                $configuration,
                $context,
                $defaultValue
            );

            $details = new ResolutionDetailsImpl();
            $details->setValue((float) $value);
            $details->setReason('TARGETING_MATCH');

            return $details;
        } catch (\Exception $e) {
            $this->logError('Float evaluation failed', [
                'flagKey' => $flagKey,
                'error' => $e->getMessage(),
            ]);

            $details = new ResolutionDetailsImpl();
            $details->setValue($defaultValue);
            $details->setReason(Reason::ERROR);

            return $details;
        }
    }

    public function resolveObjectValue(
        string $flagKey,
        bool | string | int | float | \DateTime | array | null $defaultValue,
        ?EvaluationContext $context = null
    ): ResolutionDetails {
        try {
            $context = $context ?? new EvaluationContextImpl();
            $configuration = $this->configurationManager->getConfiguration();

            // Ensure defaultValue is an array for object evaluation
            $objectDefaultValue = is_array($defaultValue) ? $defaultValue : [];

            $value = $this->evaluator->evaluateObject(
                $flagKey,
                $configuration,
                $context,
                $objectDefaultValue
            );

            $details = new ResolutionDetailsImpl();
            $details->setValue($value);
            $details->setReason('TARGETING_MATCH');

            return $details;
        } catch (\Exception $e) {
            $this->logError('Object evaluation failed', [
                'flagKey' => $flagKey,
                'error' => $e->getMessage(),
            ]);

            $details = new ResolutionDetailsImpl();
            $details->setValue($defaultValue);
            $details->setReason(Reason::ERROR);

            return $details;
        }
    }

        /**
     * Create configuration source based on configuration
     *
     * @param Configuration $config Provider configuration
     * @return ConfigurationSourceInterface Configuration source
     * @throws AwsAppConfigException
     */
    private function createConfigurationSource(Configuration $config): ConfigurationSourceInterface
    {
        return match ($config->getSourceType()) {
            ConfigurationSourceType::AWS_SDK => $this->createAwsSdkSource($config),
            ConfigurationSourceType::AGENT => $this->createAgentSource($config),
            ConfigurationSourceType::HYBRID => $this->createHybridSource($config),
        };
    }

    /**
     * Create AWS SDK configuration source
     *
     * @param Configuration $config Provider configuration
     * @return AwsSdkSource AWS SDK source
     */
    private function createAwsSdkSource(Configuration $config): AwsSdkSource
    {
        $awsConfig = array_merge([
            'version' => 'latest',
            'region' => $config->getRegion(),
            'retries' => $config->getMaxRetries(),
        ], $config->getAwsConfig());

        $appConfigClient = new AppConfigClient($awsConfig);
        return new AwsSdkSource($appConfigClient);
    }

    /**
     * Create Agent configuration source
     *
     * @param Configuration $config Provider configuration
     * @return AgentSource Agent source
     */
    private function createAgentSource(Configuration $config): AgentSource
    {
        return new AgentSource();
    }

    /**
     * Create Hybrid configuration source (future implementation)
     *
     * @param Configuration $config Provider configuration
     * @return AwsSdkSource Fallback to AWS SDK for now
     */
    private function createHybridSource(Configuration $config): AwsSdkSource
    {
        // For now, fallback to AWS SDK
        // Future implementation will combine both sources
        return $this->createAwsSdkSource($config);
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
