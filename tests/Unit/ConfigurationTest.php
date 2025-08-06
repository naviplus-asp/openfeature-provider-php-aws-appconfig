<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Tests\Unit;

use OpenFeature\Providers\AwsAppConfig\Configuration;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class ConfigurationTest extends TestCase
{
    public function testBasicConfiguration(): void
    {
        $config = new Configuration(
            application: 'test-app',
            environment: 'production',
            configurationProfile: 'feature-flags',
            region: 'us-east-1'
        );

        $this->assertEquals('test-app', $config->getApplication());
        $this->assertEquals('production', $config->getEnvironment());
        $this->assertEquals('feature-flags', $config->getConfigurationProfile());
        $this->assertEquals('us-east-1', $config->getRegion());
        $this->assertNull($config->getCache());
        $this->assertEquals(300, $config->getCacheTtl());
        $this->assertEquals(60, $config->getPollingInterval());
        $this->assertEquals(3, $config->getMaxRetries());
        $this->assertNull($config->getLogger());
        $this->assertEquals([], $config->getAwsConfig());
    }

    public function testAdvancedConfiguration(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $awsConfig = ['timeout' => 30];

        $config = new Configuration(
            application: 'test-app',
            environment: 'production',
            configurationProfile: 'feature-flags',
            region: 'us-east-1',
            cache: $cache,
            cacheTtl: 600,
            pollingInterval: 120,
            maxRetries: 5,
            logger: $logger,
            awsConfig: $awsConfig
        );

        $this->assertSame($cache, $config->getCache());
        $this->assertEquals(600, $config->getCacheTtl());
        $this->assertEquals(120, $config->getPollingInterval());
        $this->assertEquals(5, $config->getMaxRetries());
        $this->assertSame($logger, $config->getLogger());
        $this->assertEquals($awsConfig, $config->getAwsConfig());
    }
}
