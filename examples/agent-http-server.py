#!/usr/bin/env python3
"""
AppConfig Agent HTTP API Server
This server provides HTTP endpoints for feature flag evaluation
"""

import json
import os
import sys
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs
import logging

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class AppConfigAgentHandler(BaseHTTPRequestHandler):
    """HTTP request handler for AppConfig Agent API"""

    def do_GET(self):
        """Handle GET requests (health check)"""
        if self.path == '/health':
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            response = {'status': 'healthy', 'service': 'appconfig-agent'}
            self.wfile.write(json.dumps(response).encode())
        else:
            self.send_response(404)
            self.end_headers()

    def do_POST(self):
        """Handle POST requests (flag evaluation)"""
        if self.path == '/evaluate':
            self.handle_evaluate()
        else:
            self.send_response(404)
            self.end_headers()

    def handle_evaluate(self):
        """Handle flag evaluation requests"""
        try:
            # Read request body
            content_length = int(self.headers.get('Content-Length', 0))
            request_body = self.rfile.read(content_length)
            request_data = json.loads(request_body.decode('utf-8'))

            # Extract request parameters
            flag_key = request_data.get('flagKey')
            application = request_data.get('application')
            environment = request_data.get('environment')
            configuration_profile = request_data.get('configurationProfile')
            context = request_data.get('context', {})
            default_value = request_data.get('defaultValue')

            # Validate required parameters
            if not all([flag_key, application, environment, configuration_profile]):
                self.send_error_response(400, 'Missing required parameters')
                return

            # Load configuration
            config_path = self.get_config_path(application, environment, configuration_profile)
            if not os.path.exists(config_path):
                self.send_error_response(404, f'Configuration not found: {config_path}')
                return

            # Evaluate flag
            result = self.evaluate_flag(config_path, flag_key, context, default_value)

            # Send response
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(result).encode())

        except json.JSONDecodeError:
            self.send_error_response(400, 'Invalid JSON in request body')
        except Exception as e:
            logger.error(f'Evaluation error: {e}')
            self.send_error_response(500, f'Internal server error: {str(e)}')

    def get_config_path(self, application, environment, profile):
        """Get the path to the configuration file"""
        agent_path = os.getenv('AGENT_PATH', '/opt/appconfig-agent')
        return os.path.join(agent_path, 'configs', application, environment, profile, 'config.json')

    def evaluate_flag(self, config_path, flag_key, context, default_value):
        """Evaluate a feature flag"""
        try:
            with open(config_path, 'r') as f:
                config_data = json.load(f)

            features = config_data.get('features', {})
            flag_config = features.get(flag_key)

            if not flag_config:
                return {
                    'value': default_value,
                    'reason': 'FLAG_NOT_FOUND'
                }

            # Get default value from flag config
            flag_default = flag_config.get('default', default_value)

            # Evaluate rules
            rules = flag_config.get('rules', [])
            for rule in rules:
                condition = rule.get('condition')
                rule_value = rule.get('value')

                if self.evaluate_condition(condition, context):
                    return {
                        'value': rule_value,
                        'reason': 'TARGETING_MATCH'
                    }

            # Return default value if no rules match
            return {
                'value': flag_default,
                'reason': 'DEFAULT'
            }

        except Exception as e:
            logger.error(f'Flag evaluation error: {e}')
            return {
                'value': default_value,
                'reason': 'ERROR'
            }

    def evaluate_condition(self, condition, context):
        """Evaluate a condition against context"""
        if not condition:
            return True  # No condition means always match

        # Simple condition evaluation (can be extended)
        if 'user.role ==' in condition:
            expected_role = condition.split('"')[1] if '"' in condition else condition.split("'")[1]
            user_role = context.get('user', {}).get('role')
            return user_role == expected_role

        # Add more condition types here
        return False

    def send_error_response(self, status_code, message):
        """Send error response"""
        self.send_response(status_code)
        self.send_header('Content-Type', 'application/json')
        self.end_headers()
        error_response = {'error': message}
        self.wfile.write(json.dumps(error_response).encode())

    def log_message(self, format, *args):
        """Override to use our logger"""
        logger.info(f'{self.address_string()} - {format % args}')

def main():
    """Main function"""
    port = int(os.getenv('AGENT_PORT', 2772))
    host = os.getenv('AGENT_HOST', '0.0.0.0')

    server = HTTPServer((host, port), AppConfigAgentHandler)
    logger.info(f'Starting AppConfig Agent HTTP server on {host}:{port}')

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        logger.info('Shutting down server...')
        server.shutdown()

if __name__ == '__main__':
    main()
