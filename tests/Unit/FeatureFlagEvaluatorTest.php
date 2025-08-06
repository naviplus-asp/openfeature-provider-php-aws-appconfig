<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Tests\Unit;

use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\Providers\AwsAppConfig\Evaluator\FeatureFlagEvaluator;
use PHPUnit\Framework\TestCase;

class FeatureFlagEvaluatorTest extends TestCase
{
    private FeatureFlagEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new FeatureFlagEvaluator();
    }

    public function testEvaluateBooleanWithDefaultValue(): void
    {
        $configuration = [
            'features' => [
                'test-flag' => [
                    'default' => true,
                ],
            ],
        ];

        $context = new EvaluationContext();
        $result = $this->evaluator->evaluateBoolean('test-flag', $configuration, $context, false);

        $this->assertTrue($result);
    }

    public function testEvaluateBooleanWithRule(): void
    {
        $configuration = [
            'features' => [
                'test-flag' => [
                    'default' => false,
                    'rules' => [
                        [
                            'condition' => 'user.role == "admin"',
                            'value' => true,
                        ],
                    ],
                ],
            ],
        ];

        $context = new EvaluationContext(null, new \OpenFeature\implementation\flags\Attributes([
            'user' => [
                'role' => 'admin',
            ],
        ]));

        $result = $this->evaluator->evaluateBoolean('test-flag', $configuration, $context, false);

        $this->assertTrue($result);
    }

    public function testEvaluateBooleanWithNonMatchingRule(): void
    {
        $configuration = [
            'features' => [
                'test-flag' => [
                    'default' => false,
                    'rules' => [
                        [
                            'condition' => 'user.role == "admin"',
                            'value' => true,
                        ],
                    ],
                ],
            ],
        ];

        $context = new EvaluationContext(null, new \OpenFeature\implementation\flags\Attributes([
            'user' => [
                'role' => 'user',
            ],
        ]));

        $result = $this->evaluator->evaluateBoolean('test-flag', $configuration, $context, false);

        $this->assertFalse($result);
    }

    public function testEvaluateBooleanWithMissingFlag(): void
    {
        $configuration = [
            'features' => [],
        ];

        $context = new EvaluationContext();
        $result = $this->evaluator->evaluateBoolean('missing-flag', $configuration, $context, true);

        $this->assertTrue($result);
    }

    public function testEvaluateStringWithDefaultValue(): void
    {
        $configuration = [
            'features' => [
                'welcome-message' => [
                    'default' => 'Hello World',
                ],
            ],
        ];

        $context = new EvaluationContext();
        $result = $this->evaluator->evaluateString('welcome-message', $configuration, $context, 'Default');

        $this->assertEquals('Hello World', $result);
    }

    public function testEvaluateNumberWithDefaultValue(): void
    {
        $configuration = [
            'features' => [
                'discount' => [
                    'default' => 10.5,
                ],
            ],
        ];

        $context = new EvaluationContext();
        $result = $this->evaluator->evaluateNumber('discount', $configuration, $context, 0);

        $this->assertEquals(10.5, $result);
    }

    public function testEvaluateObjectWithDefaultValue(): void
    {
        $configuration = [
            'features' => [
                'user-preferences' => [
                    'default' => ['theme' => 'dark'],
                ],
            ],
        ];

        $context = new EvaluationContext();
        $result = $this->evaluator->evaluateObject('user-preferences', $configuration, $context, []);

        $this->assertEquals(['theme' => 'dark'], $result);
    }
}
