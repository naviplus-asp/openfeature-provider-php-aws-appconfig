#!/bin/bash

# Start AppConfig Agent for integration tests
echo "Starting AppConfig Agent for integration tests..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "Error: Docker is not running"
    exit 1
fi

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null; then
    echo "Error: docker-compose is not installed"
    exit 1
fi

# Create test config directory if it doesn't exist
mkdir -p tests/Integration/test-config

# Start the agent
docker-compose -f docker-compose.agent.yml up -d

# Wait for agent to be ready
echo "Waiting for AppConfig Agent to be ready..."
max_attempts=60
attempts=0

while [ $attempts -lt $max_attempts ]; do
    if curl -f http://localhost:2772/ > /dev/null 2>&1; then
        echo "AppConfig Agent is ready!"
        exit 0
    fi

    attempts=$((attempts + 1))
    sleep 3
done

echo "Error: AppConfig Agent failed to start within expected time"
exit 1
