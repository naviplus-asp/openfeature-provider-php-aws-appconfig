<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Tests\Unit\Source;

use OpenFeature\Providers\AwsAppConfig\Source\AwsSdkSource;
use PHPUnit\Framework\TestCase;

class AwsSdkSourceTest extends TestCase
{
    public function testSupportsPolling(): void
    {
        $source = $this->getMockBuilder(AwsSdkSource::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $this->assertTrue($source->supportsPolling());
    }

    public function testSupportsWebhooks(): void
    {
        $source = $this->getMockBuilder(AwsSdkSource::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $this->assertFalse($source->supportsWebhooks());
    }
}
