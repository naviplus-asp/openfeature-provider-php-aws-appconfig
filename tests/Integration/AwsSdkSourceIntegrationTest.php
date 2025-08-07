<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Tests\Integration;

use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Configuration\ConfigurationSourceType;
use OpenFeature\Providers\AwsAppConfig\AwsAppConfigProvider;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\implementation\flags\Attributes;

/**
 * Integration tests for AwsSdkSource with local AppConfig agent
 */
class AwsSdkSourceIntegrationTest extends IntegrationTestCase
{
    private AwsAppConfigProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupProvider();
    }

    /**
     * Setup the provider with AWS SDK configuration
     */
    private function setupProvider(): void
    {
        $config = new Configuration(
            application: $this->testApplication,
            environment: $this->testEnvironment,
            configurationProfile: $this->testProfile,
            region: 'us-east-1',
            sourceType: ConfigurationSourceType::AWS_SDK
        );

        $this->provider = new AwsAppConfigProvider($config);

        // Set mock configuration for testing
        $this->provider->getConfigurationManager()->setMockConfiguration($this->createSimpleTestConfig());
    }

    public function testSimpleBooleanFlagEvaluation(): void
    {
        $this->provider->getConfigurationManager()->setMockConfiguration($this->createSimpleTestConfig());

        $context = new EvaluationContext();
        $result = $this->provider->resolveBooleanValue('simple-flag', true, $context);

        $this->assertFalse($result->getValue());
        $this->assertEquals('TARGETING_MATCH', $result->getReason());
    }

    public function testStringFlagEvaluation(): void
    {
        $this->provider->getConfigurationManager()->setMockConfiguration($this->createSimpleTestConfig());

        $context = new EvaluationContext();
        $result = $this->provider->resolveStringValue('string-flag', 'fallback', $context);

        $this->assertEquals('default-value', $result->getValue());
        $this->assertEquals('TARGETING_MATCH', $result->getReason());
    }

    public function testNumberFlagEvaluation(): void
    {
        $this->provider->getConfigurationManager()->setMockConfiguration($this->createSimpleTestConfig());

        $context = new EvaluationContext();
        $result = $this->provider->resolveIntegerValue('number-flag', 0, $context);

        $this->assertEquals(42, $result->getValue());
        $this->assertEquals('TARGETING_MATCH', $result->getReason());
    }

    public function testObjectFlagEvaluation(): void
    {
        $this->provider->getConfigurationManager()->setMockConfiguration($this->createSimpleTestConfig());

        $context = new EvaluationContext();
        $result = $this->provider->resolveObjectValue('object-flag', [], $context);

        $this->assertEquals(['key' => 'value'], $result->getValue());
        $this->assertEquals('TARGETING_MATCH', $result->getReason());
    }

    public function testUserRoleTargeting(): void
    {
        $this->provider->getConfigurationManager()->setMockConfiguration($this->createTargetingTestConfig());

        // Test admin user
        $adminContext = new EvaluationContext(null, new Attributes([
            'user' => ['role' => 'admin']
        ]));
        $adminResult = $this->provider->resolveBooleanValue('user-role-flag', false, $adminContext);

        $this->assertTrue($adminResult->getValue());
        $this->assertEquals('TARGETING_MATCH', $adminResult->getReason());

        // Test moderator user
        $moderatorContext = new EvaluationContext(null, new Attributes([
            'user' => ['role' => 'moderator']
        ]));
        $moderatorResult = $this->provider->resolveBooleanValue('user-role-flag', false, $moderatorContext);

        $this->assertTrue($moderatorResult->getValue());
        $this->assertEquals('TARGETING_MATCH', $moderatorResult->getReason());

        // Test regular user (should get default value)
        $userContext = new EvaluationContext(null, new Attributes([
            'user' => ['role' => 'user']
        ]));
        $userResult = $this->provider->resolveBooleanValue('user-role-flag', false, $userContext);

        $this->assertFalse($userResult->getValue());
        $this->assertEquals('TARGETING_MATCH', $userResult->getReason());
    }

    public function testRegionTargeting(): void
    {
        $this->provider->getConfigurationManager()->setMockConfiguration($this->createTargetingTestConfig());

        // Test US user
        $usContext = new EvaluationContext(null, new Attributes([
            'user' => ['region' => 'us']
        ]));
        $usResult = $this->provider->resolveStringValue('region-flag', 'fallback', $usContext);

        $this->assertEquals('us-specific', $usResult->getValue());
        $this->assertEquals('TARGETING_MATCH', $usResult->getReason());

        // Test JP user
        $jpContext = new EvaluationContext(null, new Attributes([
            'user' => ['region' => 'jp']
        ]));
        $jpResult = $this->provider->resolveStringValue('region-flag', 'fallback', $jpContext);

        $this->assertEquals('jp-specific', $jpResult->getValue());
        $this->assertEquals('TARGETING_MATCH', $jpResult->getReason());

        // Test unknown region (should get default value)
        $unknownContext = new EvaluationContext(null, new Attributes([
            'user' => ['region' => 'unknown']
        ]));
        $unknownResult = $this->provider->resolveStringValue('region-flag', 'fallback', $unknownContext);

        $this->assertEquals('global', $unknownResult->getValue());
        $this->assertEquals('TARGETING_MATCH', $unknownResult->getReason());
    }

    public function testMissingFlagReturnsDefaultValue(): void
    {
        $this->provider->getConfigurationManager()->setMockConfiguration($this->createSimpleTestConfig());

        $context = new EvaluationContext();
        $result = $this->provider->resolveBooleanValue('missing-flag', true, $context);

        $this->assertTrue($result->getValue());
        $this->assertEquals('TARGETING_MATCH', $result->getReason());
    }

    public function testComplexContextEvaluation(): void
    {
        $config = [
            'version' => '1.0',
            'flags' => [
                'complex-flag' => [
                    'state' => 'ENABLED',
                    'defaultVariants' => [
                        'string' => 'default'
                    ],
                    'targeting' => [
                        'rules' => [
                            [
                                'condition' => 'user.profile.tier == "premium"',
                                'variants' => [
                                    'string' => 'premium-feature'
                                ]
                            ],
                            [
                                'condition' => 'request.headers.x-feature == "beta"',
                                'variants' => [
                                    'string' => 'beta-feature'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->provider->getConfigurationManager()->setMockConfiguration($config);

        // Test premium user
        $premiumContext = new EvaluationContext(null, new Attributes([
            'user' => [
                'profile' => ['tier' => 'premium']
            ]
        ]));
        $premiumResult = $this->provider->resolveStringValue('complex-flag', 'fallback', $premiumContext);

        $this->assertEquals('premium-feature', $premiumResult->getValue());

        // Test beta request
        $betaContext = new EvaluationContext(null, new Attributes([
            'request' => [
                'headers' => ['x-feature' => 'beta']
            ]
        ]));
        $betaResult = $this->provider->resolveStringValue('complex-flag', 'fallback', $betaContext);

        $this->assertEquals('beta-feature', $betaResult->getValue());
    }

    public function testMultipleFlagEvaluations(): void
    {
        $this->provider->getConfigurationManager()->setMockConfiguration($this->createSimpleTestConfig());

        $context = new EvaluationContext();

        // Evaluate multiple flags
        $results = [];
        $flags = ['simple-flag', 'string-flag', 'number-flag', 'object-flag'];

        foreach ($flags as $flag) {
            $results[$flag] = $this->provider->resolveBooleanValue($flag, true, $context);
        }

        // Verify all evaluations completed
        $this->assertCount(4, $results);
        $this->assertArrayHasKey('simple-flag', $results);
        $this->assertArrayHasKey('string-flag', $results);
        $this->assertArrayHasKey('number-flag', $results);
        $this->assertArrayHasKey('object-flag', $results);
    }

    public function testEmptyConfiguration(): void
    {
        $this->provider->getConfigurationManager()->setMockConfiguration([]);

        $context = new EvaluationContext();
        $result = $this->provider->resolveBooleanValue('any-flag', true, $context);

        $this->assertTrue($result->getValue());
        $this->assertEquals('TARGETING_MATCH', $result->getReason());
    }

    public function testConfigurationWithNoFeatures(): void
    {
        $this->provider->getConfigurationManager()->setMockConfiguration(['flags' => []]);

        $context = new EvaluationContext();
        $result = $this->provider->resolveBooleanValue('any-flag', true, $context);

        $this->assertTrue($result->getValue());
        $this->assertEquals('TARGETING_MATCH', $result->getReason());
    }
}
