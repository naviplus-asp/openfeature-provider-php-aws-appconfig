<?php

require_once __DIR__ . '/LocalAgentServer.php';

// Create and start the agent server
$server = new LocalAgentServer('0.0.0.0', 2772);

// Load test configuration
$config = json_decode(file_get_contents(__DIR__ . '/test-config/feature-flags.json'), true);
$server->addConfiguration('test-app', 'test', 'feature-flags', $config);

// Start the server
$server->start();

echo "AppConfig Agent Server started on http://0.0.0.0:2772\n";

// Handle requests
while ($server->isRunning()) {
    $server->handleRequest();
    usleep(10000); // 10ms delay
}
