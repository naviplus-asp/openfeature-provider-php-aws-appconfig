<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Tests\Integration;

use PHPUnit\Framework\TestCase;
use OpenFeature\Providers\AwsAppConfig\Tests\Integration\LocalAgentServer;

/**
 * Base class for integration tests with local AppConfig agent
 */
abstract class IntegrationTestCase extends TestCase
{
    protected $agentServer;
    protected string $testApplication = 'test-app';
    protected string $testEnvironment = 'test';
    protected string $testProfile = 'feature-flags';

    protected function setUp(): void
    {
        parent::setUp();
        // Check if Docker agent is available, otherwise use mock data
        if ($this->isDockerAgentAvailable()) {
            $this->startAgentServer();
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->agentServer) && $this->agentServer->isRunning()) {
            $this->stopAgentServer();
        }
        parent::tearDown();
    }

    /**
     * Start the local agent server
     */
    protected function startAgentServer(): void
    {
        // Use Docker agent instead of local server
        $this->agentServer = new class {
            public function isRunning(): bool
            {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 1,
                        'method' => 'GET'
                    ]
                ]);
                $response = @file_get_contents('http://localhost:2772/', false, $context);
                return $response !== false;
            }

            public function getAddress(): string
            {
                return 'http://localhost:2772';
            }

            public function stop(): void
            {
                // Docker agent will be stopped by docker-compose
            }
        };

        // Wait for server to be ready
        $this->waitForServerReady();
    }

    /**
     * Stop the local agent server
     */
    protected function stopAgentServer(): void
    {
        if (isset($this->agentServer)) {
            $this->agentServer->stop();
        }
    }

    /**
     * Check if Docker agent is available
     */
    protected function isDockerAgentAvailable(): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'method' => 'GET'
            ]
        ]);

        $response = @file_get_contents('http://localhost:2772/', false, $context);
        return $response !== false;
    }

    /**
     * Wait for server to be ready
     */
    protected function waitForServerReady(): void
    {
        $maxAttempts = 30; // Increase attempts for Docker agent
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            try {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 2,
                        'method' => 'GET'
                    ]
                ]);

                $response = @file_get_contents('http://localhost:2772/', false, $context);
                if ($response !== false) {
                    return; // Server is ready
                }
            } catch (\Exception $e) {
                // Server not ready yet
            }

            $attempts++;
            usleep(500000); // Wait 500ms
        }

        throw new \RuntimeException('Agent server failed to start');
    }

    /**
     * Add test configuration to the agent server
     */
    protected function addTestConfiguration(array $config): void
    {
        $this->agentServer->addConfiguration(
            $this->testApplication,
            $this->testEnvironment,
            $this->testProfile,
            $config
        );
    }

    /**
     * Create a simple test configuration
     */
    protected function createSimpleTestConfig(): array
    {
        return [
            'version' => '1.0',
            'flags' => [
                'simple-flag' => [
                    'state' => 'ENABLED',
                    'defaultVariants' => [
                        'boolean' => false
                    ]
                ],
                'string-flag' => [
                    'state' => 'ENABLED',
                    'defaultVariants' => [
                        'string' => 'default-value'
                    ]
                ],
                'number-flag' => [
                    'state' => 'ENABLED',
                    'defaultVariants' => [
                        'number' => 42
                    ]
                ],
                'object-flag' => [
                    'state' => 'ENABLED',
                    'defaultVariants' => [
                        'object' => ['key' => 'value']
                    ]
                ]
            ]
        ];
    }

    /**
     * Create a test configuration with targeting rules
     */
    protected function createTargetingTestConfig(): array
    {
        return [
            'version' => '1.0',
            'flags' => [
                'user-role-flag' => [
                    'state' => 'ENABLED',
                    'defaultVariants' => [
                        'boolean' => false
                    ],
                    'targeting' => [
                        'rules' => [
                            [
                                'condition' => 'user.role == "admin"',
                                'variants' => [
                                    'boolean' => true
                                ]
                            ],
                            [
                                'condition' => 'user.role == "moderator"',
                                'variants' => [
                                    'boolean' => true
                                ]
                            ]
                        ]
                    ]
                ],
                'region-flag' => [
                    'state' => 'ENABLED',
                    'defaultVariants' => [
                        'string' => 'global'
                    ],
                    'targeting' => [
                        'rules' => [
                            [
                                'condition' => 'user.region == "us"',
                                'variants' => [
                                    'string' => 'us-specific'
                                ]
                            ],
                            [
                                'condition' => 'user.region == "jp"',
                                'variants' => [
                                    'string' => 'jp-specific'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Make a test request to the agent server
     */
    protected function makeAgentRequest(string $flagKey, array $context = [], mixed $defaultValue = null): array
    {
        $request = [
            'flagKey' => $flagKey,
            'application' => $this->testApplication,
            'environment' => $this->testEnvironment,
            'configurationProfile' => $this->testProfile,
            'context' => $context,
            'defaultValue' => $defaultValue
        ];

        $httpContext = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                'content' => json_encode($request),
                'timeout' => 10
            ]
        ]);

        $url = $this->agentServer->getAddress() . '/evaluate';
        $response = file_get_contents($url, false, $httpContext);

        if ($response === false) {
            throw new \RuntimeException('Failed to make request to agent server');
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response from agent server');
        }

        return $result;
    }

    /**
     * Assert that the agent server is running
     */
    protected function assertAgentServerRunning(): void
    {
        $this->assertTrue($this->agentServer->isRunning());
    }

    /**
     * Assert that a flag evaluation returns the expected value
     */
    protected function assertFlagEvaluation(
        string $flagKey,
        mixed $expectedValue,
        array $context = [],
        mixed $defaultValue = null
    ): void {
        $result = $this->makeAgentRequest($flagKey, $context, $defaultValue);

        $this->assertArrayHasKey('value', $result);
        $this->assertEquals($expectedValue, $result['value']);
    }

    /**
     * Assert that a flag evaluation returns the expected reason
     */
    protected function assertFlagReason(
        string $flagKey,
        string $expectedReason,
        array $context = [],
        mixed $defaultValue = null
    ): void {
        $result = $this->makeAgentRequest($flagKey, $context, $defaultValue);

        $this->assertArrayHasKey('reason', $result);
        $this->assertEquals($expectedReason, $result['reason']);
    }
}
