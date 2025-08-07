<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Tests\Integration;

use OpenFeature\Providers\AwsAppConfig\Tests\Integration\LocalAgentServer;

/**
 * Local AppConfig Agent Server for Integration Tests
 */
class LocalAgentServer
{
    private int $port;
    private string $host;
    private $socket;
    private bool $running = false;
    private array $configurations = [];

    public function __construct(string $host = '127.0.0.1', int $port = 2772)
    {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * Start the local agent server
     */
    public function start(): void
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new \RuntimeException('Failed to create socket');
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (!socket_bind($this->socket, $this->host, $this->port)) {
            throw new \RuntimeException('Failed to bind socket');
        }

        if (!socket_listen($this->socket, 5)) {
            throw new \RuntimeException('Failed to listen on socket');
        }

        $this->running = true;
    }

    /**
     * Stop the local agent server
     */
    public function stop(): void
    {
        if ($this->socket) {
            socket_close($this->socket);
        }
        $this->running = false;
    }

    /**
     * Add configuration data
     */
    public function addConfiguration(string $application, string $environment, string $profile, array $config): void
    {
        $key = "{$application}/{$environment}/{$profile}";
        $this->configurations[$key] = $config;
    }

    /**
     * Handle HTTP requests
     */
    public function handleRequest(): void
    {
        if (!$this->running) {
            return;
        }

        $client = socket_accept($this->socket);
        if ($client === false) {
            return;
        }

        $request = socket_read($client, 4096);
        if ($request === false) {
            socket_close($client);
            return;
        }

        $response = $this->processRequest($request);
        socket_write($client, $response);
        socket_close($client);
    }

    /**
     * Process HTTP request and return response
     */
    private function processRequest(string $request): string
    {
        $lines = explode("\n", $request);
        $firstLine = $lines[0] ?? '';

        if (preg_match('/^(\w+)\s+([^\s]+)\s+HTTP/', $firstLine, $matches)) {
            $method = $matches[1];
            $path = $matches[2];

            if ($method === 'GET' && $path === '/health') {
                return $this->handleHealthCheck();
            }

            if ($method === 'POST' && $path === '/evaluate') {
                return $this->handleEvaluate($request);
            }
        }

        return $this->createResponse(404, 'Not Found');
    }

    /**
     * Handle health check request
     */
    private function handleHealthCheck(): string
    {
        $response = [
            'status' => 'healthy',
            'service' => 'appconfig-agent',
            'timestamp' => time()
        ];

        return $this->createResponse(200, json_encode($response));
    }

    /**
     * Handle flag evaluation request
     */
    private function handleEvaluate(string $request): string
    {
        // Extract JSON body from request
        $bodyStart = strpos($request, "\r\n\r\n");
        if ($bodyStart === false) {
            return $this->createResponse(400, 'Invalid request');
        }

        $body = substr($request, $bodyStart + 4);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->createResponse(400, 'Invalid JSON');
        }

        $flagKey = $data['flagKey'] ?? '';
        $application = $data['application'] ?? '';
        $environment = $data['environment'] ?? '';
        $profile = $data['configurationProfile'] ?? '';
        $context = $data['context'] ?? [];
        $defaultValue = $data['defaultValue'] ?? null;

        if (empty($flagKey) || empty($application) || empty($environment) || empty($profile)) {
            return $this->createResponse(400, 'Missing required parameters');
        }

        $result = $this->evaluateFlag($flagKey, $application, $environment, $profile, $context, $defaultValue);
        return $this->createResponse(200, json_encode($result));
    }

    /**
     * Evaluate a feature flag
     */
    private function evaluateFlag(
        string $flagKey,
        string $application,
        string $environment,
        string $profile,
        array $context,
        mixed $defaultValue
    ): array {
        $configKey = "{$application}/{$environment}/{$profile}";
        $configuration = $this->configurations[$configKey] ?? [];

        $features = $configuration['features'] ?? [];
        $flag = $features[$flagKey] ?? null;

        if ($flag === null) {
            return [
                'value' => $defaultValue,
                'reason' => 'FLAG_NOT_FOUND'
            ];
        }

        // Get default value from flag config
        $flagDefault = $flag['default'] ?? $defaultValue;

        // Evaluate rules
        $rules = $flag['rules'] ?? [];
        foreach ($rules as $rule) {
            $condition = $rule['condition'] ?? null;
            $ruleValue = $rule['value'] ?? null;

            if ($this->evaluateCondition($condition, $context)) {
                return [
                    'value' => $ruleValue,
                    'reason' => 'TARGETING_MATCH'
                ];
            }
        }

        // Return default value if no rules match
        return [
            'value' => $flagDefault,
            'reason' => 'DEFAULT'
        ];
    }

    /**
     * Evaluate a condition against context
     */
    private function evaluateCondition(?string $condition, array $context): bool
    {
        if ($condition === null) {
            return true; // No condition means always match
        }

        // Simple condition evaluation
        $pattern = '/^(\w+(?:\.\w+)*)\s*==\s*["\']([^"\']*)["\']$/';
        if (preg_match($pattern, $condition, $matches)) {
            $path = $matches[1];
            $expectedValue = $matches[2];

            $actualValue = $this->getValueFromContext($path, $context);
            return $actualValue === $expectedValue;
        }

        return false;
    }

    /**
     * Get value from context using dot notation
     */
    private function getValueFromContext(string $path, array $context): mixed
    {
        $keys = explode('.', $path);
        $value = $context;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Create HTTP response
     */
    private function createResponse(int $statusCode, string $body): string
    {
        $statusText = $this->getStatusText($statusCode);
        $contentLength = strlen($body);

        return "HTTP/1.1 {$statusCode} {$statusText}\r\n" .
               "Content-Type: application/json\r\n" .
               "Content-Length: {$contentLength}\r\n" .
               "Connection: close\r\n" .
               "\r\n" .
               $body;
    }

    /**
     * Get HTTP status text
     */
    private function getStatusText(int $statusCode): string
    {
        return match ($statusCode) {
            200 => 'OK',
            400 => 'Bad Request',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            default => 'Unknown'
        };
    }

    /**
     * Check if server is running
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Get server address
     */
    public function getAddress(): string
    {
        return "http://{$this->host}:{$this->port}";
    }
}
