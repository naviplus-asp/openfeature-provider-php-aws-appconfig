<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Tests\Unit\Source;

use OpenFeature\Providers\AwsAppConfig\Source\AgentSource;
use PHPUnit\Framework\TestCase;

class AgentSourceTest extends TestCase
{
    public function testSupportsPolling(): void
    {
        $source = new AgentSource();
        $this->assertTrue($source->supportsPolling());
    }

    public function testSupportsWebhooks(): void
    {
        $source = new AgentSource();
        $this->assertTrue($source->supportsWebhooks());
    }
}
