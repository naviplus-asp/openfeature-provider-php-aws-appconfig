<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Exception;

/**
 * Exception thrown when configuration is not found in AWS AppConfig
 */
class ConfigurationNotFoundException extends AwsAppConfigException
{
    public function __construct(
        string $application,
        string $environment,
        string $configurationProfile,
        ?\Throwable $previous = null,
        ?string $additionalMessage = null
    ) {
        $message = sprintf(
            'Configuration not found: application=%s, environment=%s, profile=%s',
            $application,
            $environment,
            $configurationProfile
        );

        if ($additionalMessage !== null) {
            $message .= ' - ' . $additionalMessage;
        }

        parent::__construct($message, 0, $previous);
    }
}
