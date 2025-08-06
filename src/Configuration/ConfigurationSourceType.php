<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Configuration;

/**
 * Configuration source types
 */
enum ConfigurationSourceType: string
{
    case AWS_SDK = 'aws_sdk';
    case AGENT = 'agent';
    case HYBRID = 'hybrid';

    /**
     * Get the display name for the source type
     *
     * @return string Display name
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::AWS_SDK => 'AWS SDK',
            self::AGENT => 'AppConfig Agent',
            self::HYBRID => 'Hybrid (AWS SDK + Agent)',
        };
    }

    /**
     * Get the description for the source type
     *
     * @return string Description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::AWS_SDK => 'Direct AWS SDK integration with API calls',
            self::AGENT => 'Local AppConfig agent for improved performance',
            self::HYBRID => 'Combined approach with fallback mechanisms',
        };
    }
}
