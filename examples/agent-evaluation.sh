#!/bin/bash

# AppConfig Agent Evaluation Script
# This script demonstrates how the agent can evaluate feature flags with context

set -e

# Default values
AGENT_PATH="/opt/appconfig-agent"
FLAG_KEY=""
APPLICATION=""
ENVIRONMENT=""
PROFILE=""
CONTEXT="{}"
DEFAULT_VALUE=""

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --flag-key)
            FLAG_KEY="$2"
            shift 2
            ;;
        --application)
            APPLICATION="$2"
            shift 2
            ;;
        --environment)
            ENVIRONMENT="$2"
            shift 2
            ;;
        --profile)
            PROFILE="$2"
            shift 2
            ;;
        --context)
            CONTEXT="$2"
            shift 2
            ;;
        --default)
            DEFAULT_VALUE="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Validate required parameters
if [[ -z "$FLAG_KEY" || -z "$APPLICATION" || -z "$ENVIRONMENT" || -z "$PROFILE" ]]; then
    echo "Usage: $0 --flag-key <key> --application <app> --environment <env> --profile <profile> [--context <json>] [--default <value>]"
    exit 1
fi

# Load configuration
CONFIG_PATH="$AGENT_PATH/configs/$APPLICATION/$ENVIRONMENT/$PROFILE/config.json"

if [[ ! -f "$CONFIG_PATH" ]]; then
    echo "Configuration file not found: $CONFIG_PATH" >&2
    exit 1
fi

# Parse context
CONTEXT_DATA=$(echo "$CONTEXT" | jq -r '.' 2>/dev/null || echo "{}")

# Load feature flag configuration
FEATURE_CONFIG=$(jq -r ".features[\"$FLAG_KEY\"]" "$CONFIG_PATH" 2>/dev/null || echo "null")

if [[ "$FEATURE_CONFIG" == "null" ]]; then
    # Flag not found, return default value
    echo "{\"value\": $(echo "$DEFAULT_VALUE" | jq -r '.'), \"reason\": \"FLAG_NOT_FOUND\"}"
    exit 0
fi

# Extract flag data
DEFAULT_FLAG_VALUE=$(echo "$FEATURE_CONFIG" | jq -r '.default // empty')
RULES=$(echo "$FEATURE_CONFIG" | jq -r '.rules // []')

# Evaluate rules
if [[ $(echo "$RULES" | jq 'length') -gt 0 ]]; then
    # Process each rule
    for i in $(seq 0 $(($(echo "$RULES" | jq 'length') - 1))); do
        RULE=$(echo "$RULES" | jq ".[$i]")
        CONDITION=$(echo "$RULE" | jq -r '.condition // empty')
        RULE_VALUE=$(echo "$RULE" | jq -r '.value // empty')

        if [[ -n "$CONDITION" ]]; then
            # Evaluate condition (simplified implementation)
            if [[ "$CONDITION" == *"user.role"* ]]; then
                USER_ROLE=$(echo "$CONTEXT_DATA" | jq -r '.user.role // empty')
                EXPECTED_ROLE=$(echo "$CONDITION" | sed 's/.*== *"\([^"]*\)".*/\1/')

                if [[ "$USER_ROLE" == "$EXPECTED_ROLE" ]]; then
                    echo "{\"value\": $(echo "$RULE_VALUE" | jq -r '.'), \"reason\": \"TARGETING_MATCH\"}"
                    exit 0
                fi
            fi
        else
            # No condition, always match
            echo "{\"value\": $(echo "$RULE_VALUE" | jq -r '.'), \"reason\": \"TARGETING_MATCH\"}"
            exit 0
        fi
    done
fi

# No rules matched, return default value
if [[ -n "$DEFAULT_FLAG_VALUE" ]]; then
    echo "{\"value\": $(echo "$DEFAULT_FLAG_VALUE" | jq -r '.'), \"reason\": \"DEFAULT\"}"
else
    echo "{\"value\": $(echo "$DEFAULT_VALUE" | jq -r '.'), \"reason\": \"DEFAULT\"}"
fi
