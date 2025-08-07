<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Configuration\ConfigurationSourceType;
use OpenFeature\Providers\AwsAppConfig\AwsAppConfigProvider;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\implementation\flags\Attributes;

// HTTP通信ベースのAgentSourceを使用する設定
$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1',
    sourceType: ConfigurationSourceType::AGENT,
    agentHost: $_ENV['APP_CONFIG_AGENT_HOST'] ?? 'localhost',
    agentPort: (int)($_ENV['APP_CONFIG_AGENT_PORT'] ?? 2772)
);

$provider = new AwsAppConfigProvider($config);

// コンテキストを作成
$context = new EvaluationContext(null, new Attributes([
    'user' => [
        'id' => 'user-123',
        'role' => 'admin',
        'region' => 'us-east-1'
    ],
    'request' => [
        'ip' => '192.168.1.1',
        'userAgent' => 'Mozilla/5.0...'
    ]
]));

// フラグ評価（HTTP通信でAgentに評価を委譲）
try {
    $result = $provider->resolveBooleanValue('new-feature', false, $context);

    echo "Flag evaluation result:\n";
    echo "Value: " . ($result->getValue() ? 'true' : 'false') . "\n";
    echo "Reason: " . $result->getReason() . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// 文字列フラグの評価
try {
    $messageResult = $provider->resolveStringValue('welcome-message', 'Hello', $context);

    echo "Welcome message: " . $messageResult->getValue() . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// オブジェクトフラグの評価
try {
    $configResult = $provider->resolveObjectValue('user-config', [], $context);

    echo "User config: " . json_encode($configResult->getValue()) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
