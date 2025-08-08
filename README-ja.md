# OpenFeature PHP AWS AppConfig Provider（日本語）

このプロジェクトは、AWS AppConfig をバックエンドとする OpenFeature の PHP プロバイダーです。機能フラグ（Feature Flags）を OpenFeature の標準 API で評価でき、AWS AppConfig（または AppConfig Agent）から設定を取得します。

このファイルは日本語版 README です。英語版は `README.md` を参照してください。

## 特長

- OpenFeature 仕様に準拠
- AWS AppConfig との統合（AWS SDK / AppConfig Agent）
- すべての評価タイプに対応（boolean / string / number / object）
- TTL ベースのキャッシュ対応（PSR-6）
- エラー処理とフォールバック
- PSR-3 ロギング対応
- 高いテストカバレッジ

## インストール

```bash
composer require openfeature/php-aws-appconfig-provider
```

## クイックスタート

```php
<?php

use OpenFeature\OpenFeatureAPI;
use OpenFeature\Providers\AwsAppConfig\AwsAppConfigProvider;
use OpenFeature\Providers\AwsAppConfig\Configuration;

$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1'
);

$provider = new AwsAppConfigProvider($config);

$api = OpenFeatureAPI::getInstance();
$api->setProvider($provider);

$client = $api->getClient();
$isEnabled = $client->getBooleanValue('my-feature', false);
```

## 設定

### 基本設定（AWS SDK）

```php
use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Configuration\ConfigurationSourceType;

$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1' // 既定は AWS_SDK ソース
);
```

### AppConfig Agent を使う

```php
use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Configuration\ConfigurationSourceType;

$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1',
    sourceType: ConfigurationSourceType::AGENT,
    enablePolling: true,
    pollingInterval: 60
);
```

### キャッシュ設定（PSR-6）

```php
use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Cache\Psr6Cache;

$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1',
    cache: new Psr6Cache($cachePool),
    cacheTtl: 300,
    pollingInterval: 60,
    maxRetries: 3
);
```

## 設定ソース

1. AWS SDK（デフォルト）: 直接 AWS AppConfig API を呼び出します。
2. AppConfig Agent: ローカルエージェント経由で低レイテンシ・低コスト。

## AWS AppConfig の準備

1. Application の作成
2. Environment の作成
3. Configuration Profile の作成
4. フィーチャーフラグを設定
5. デプロイ

## 使用例

```php
// Boolean
$isEnabled = $client->getBooleanValue('my-feature', false);

// String
$message = $client->getStringValue('welcome-message', 'Hello');

// Number
$percentage = $client->getNumberValue('discount-percentage', 0);

// Object
$prefs = $client->getObjectValue('user-preferences', ['theme' => 'light']);
```

## エラー処理

```php
try {
    $value = $client->getBooleanValue('my-feature', false);
} catch (\Throwable $e) {
    error_log('Feature flag error: ' . $e->getMessage());
    $value = false; // フォールバック
}
```

## ロギング

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('openfeature');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1',
    logger: $logger
);
```

## サイドカーパターン（AppConfig Agent）

本プロバイダーは AppConfig Agent をサイドカーとして使う構成を推奨します。アプリケーションと同一ホスト（同一 Pod）で動作し、レイテンシ低減・可用性向上が見込めます。

### Docker Compose 例

```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8000:8000"
    environment:
      - APP_CONFIG_AGENT_HOST=agent
      - APP_CONFIG_AGENT_PORT=2772
    depends_on:
      agent:
        condition: service_healthy
    networks:
      - app-network

  agent:
    image: public.ecr.aws/aws-appconfig/aws-appconfig-agent:latest
    environment:
      - AWS_REGION=us-east-1
      - AWS_APPCONFIG_APPLICATION=my-app
      - AWS_APPCONFIG_ENVIRONMENT=production
      - AWS_APPCONFIG_CONFIGURATION_PROFILE=feature-flags
    ports:
      - "2772:2772"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:2772/health"]
      interval: 30s
      timeout: 10s
      retries: 3
    networks:
      - app-network

networks:
  app-network:
    driver: bridge
```

### アプリ側設定例（サイドカー利用時）

```php
use OpenFeature\Providers\AwsAppConfig\Configuration;
use OpenFeature\Providers\AwsAppConfig\Configuration\ConfigurationSourceType;

$config = new Configuration(
    application: 'my-app',
    environment: 'production',
    configurationProfile: 'feature-flags',
    region: 'us-east-1',
    sourceType: ConfigurationSourceType::AGENT,
    enablePolling: true,
    pollingInterval: 60
);
```

### Kubernetes 例（サイドカー）

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: my-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: my-app
  template:
    metadata:
      labels:
        app: my-app
    spec:
      containers:
      - name: app
        image: my-app:latest
        env:
        - name: APP_CONFIG_AGENT_HOST
          value: "localhost"
        - name: APP_CONFIG_AGENT_PORT
          value: "2772"
      - name: appconfig-agent
        image: public.ecr.aws/aws-appconfig/aws-appconfig-agent:latest
        ports:
        - containerPort: 2772
        livenessProbe:
          httpGet:
            path: /health
            port: 2772
          initialDelaySeconds: 30
          periodSeconds: 30
        readinessProbe:
          httpGet:
            path: /health
            port: 2772
          initialDelaySeconds: 5
          periodSeconds: 10
```

## トラブルシューティング

- Agent が起動しているか確認: `curl http://localhost:2772/health`
- IAM 権限の確認（AppConfig へのアクセス権）
- Application / Environment / Configuration Profile の存在確認
- ログの確認（Agent / アプリケーション）

## 開発・テスト

```bash
# 依存関係のインストール
composer install

# テスト
composer test

# 静的解析 / コードスタイル
composer phpstan
composer phpcs
```

## サポート

- OpenFeature ドキュメント: https://openfeature.dev/docs
- AWS AppConfig ドキュメント: https://docs.aws.amazon.com/appconfig/
- Issues: https://github.com/naviplus-asp/openfeature-provider-php-aws-appconfig/issues


