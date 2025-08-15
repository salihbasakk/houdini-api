#!/bin/bash

# Telemetry API Test with Complete Data
echo "Testing Telemetry API with complete data..."

curl -X POST http://localhost/api/telemetry \
  -H "Content-Type: application/json" \
  -d '{
    "project_id": "my-project-123",
    "telemetry_data": [
      {
        "type": "trace",
        "operation_name": "GET /api/users",
        "trace_id": "abc123...",
        "span_id": "def456...",
        "start_time": 1640995200.123,
        "end_time": 1640995200.456,
        "duration": 0.333,
        "attributes": {
          "http.method": "GET",
          "http.status_code": 200,
          "http.url": "https://example.com/api/users"
        },
        "service_name": "my-symfony-app",
        "service_version": "1.0.0"
      },
      {
        "type": "metric",
        "name": "http.request.duration",
        "value": 0.333,
        "timestamp": 1640995200.456,
        "attributes": {
          "method": "GET",
          "status_code": 200,
          "route": "api_users"
        },
        "service_name": "my-symfony-app",
        "service_version": "1.0.0"
      },
      {
        "type": "exception",
        "class": "App\\Exception\\ValidationException",
        "message": "Invalid input data",
        "file": "/app/src/Controller/UserController.php",
        "line": 45,
        "trace": "Exception trace here...",
        "timestamp": 1640995200.789,
        "context": {
          "request_uri": "/api/users",
          "user_id": 123
        },
        "service_name": "my-symfony-app",
        "service_version": "1.0.0"
      }
    ],
    "metadata": {
      "service_name": "my-symfony-app",
      "service_version": "1.0.0",
      "timestamp": 1640995200.999,
      "sdk_version": "1.0.0"
    }
  }'

echo -e "\n\n"

# Test with another project data
echo "Adding more test data for my-project-123..."

curl -X POST http://localhost/api/telemetry \
  -H "Content-Type: application/json" \
  -d '{
    "project_id": "my-project-123",
    "telemetry_data": [
      {
        "type": "trace",
        "operation_name": "POST /api/orders",
        "trace_id": "xyz789",
        "duration": 0.125
      }
    ],
    "metadata": {
      "service_name": "order-service",
      "service_version": "2.0.0"
    }
  }'

echo -e "\n\n"

# Test with different project
echo "Adding data for different project..."

curl -X POST http://localhost/api/telemetry \
  -H "Content-Type: application/json" \
  -d '{
    "project_id": "other-project-456",
    "telemetry_data": [
      {
        "type": "metric",
        "name": "cpu.usage",
        "value": 85.5
      }
    ],
    "metadata": {
      "service_name": "monitoring-service"
    }
  }'

echo -e "\n\n"

# GET REQUESTS - Retrieve telemetry logs with pagination

echo "=== GET REQUESTS - Retrieving telemetry logs ==="
echo ""

# Get first page of logs for my-project-123
echo "Getting first page of logs for my-project-123..."
curl -X GET "http://localhost/api/telemetry/my-project-123?page=1"

echo -e "\n\n"

# Get second page of logs for my-project-123
echo "Getting second page of logs for my-project-123..."
curl -X GET "http://localhost/api/telemetry/my-project-123?page=2"

echo -e "\n\n"

# Get logs for other-project-456
echo "Getting logs for other-project-456..."
curl -X GET "http://localhost/api/telemetry/other-project-456"

echo -e "\n\n"

# Get logs for non-existent project
echo "Getting logs for non-existent project..."
curl -X GET "http://localhost/api/telemetry/non-existent-project"

echo -e "\n\n"

# Test error case - missing project_id
echo "Testing error case - missing project_id..."

curl -X POST http://localhost/api/telemetry \
  -H "Content-Type: application/json" \
  -d '{
    "telemetry_data": [
      {
        "type": "trace",
        "operation_name": "Test Operation"
      }
    ]
  }'

echo -e "\n\n"

# Test error case - invalid JSON
echo "Testing error case - invalid JSON..."

curl -X POST http://localhost/api/telemetry \
  -H "Content-Type: application/json" \
  -d '{"invalid": json}'
