<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Tests\Unit;

use OpenFeature\Providers\AwsAppConfig\Configuration\ConfigurationSourceType;
use PHPUnit\Framework\TestCase;

class ConfigurationSourceTypeTest extends TestCase
{
    public function testAwsSdkSourceType(): void
    {
        $sourceType = ConfigurationSourceType::AWS_SDK;

        $this->assertEquals('aws_sdk', $sourceType->value);
        $this->assertEquals('AWS SDK', $sourceType->getDisplayName());
        $this->assertEquals('Direct AWS SDK integration with API calls', $sourceType->getDescription());
    }

    public function testAgentSourceType(): void
    {
        $sourceType = ConfigurationSourceType::AGENT;

        $this->assertEquals('agent', $sourceType->value);
        $this->assertEquals('AppConfig Agent', $sourceType->getDisplayName());
        $this->assertEquals('Local AppConfig agent for improved performance', $sourceType->getDescription());
    }

    public function testHybridSourceType(): void
    {
        $sourceType = ConfigurationSourceType::HYBRID;

        $this->assertEquals('hybrid', $sourceType->value);
        $this->assertEquals('Hybrid (AWS SDK + Agent)', $sourceType->getDisplayName());
        $this->assertEquals('Combined approach with fallback mechanisms', $sourceType->getDescription());
    }
}
