#!/bin/bash

# Stop AppConfig Agent
echo "Stopping AppConfig Agent..."

# Stop the agent
docker-compose -f docker-compose.agent.yml down

echo "AppConfig Agent stopped"
