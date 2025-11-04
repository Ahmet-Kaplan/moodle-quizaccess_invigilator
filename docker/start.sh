#!/bin/bash

# Moodle Invigilator Test Environment Startup Script

set -e

echo "=== Moodle Invigilator Test Environment ==="
echo "Starting Docker containers for Moodle 4.4.3 with Invigilator plugin testing..."

# Check if Docker and Docker Compose are available
if ! command -v docker &> /dev/null; then
    echo "Error: Docker is not installed or not in PATH"
    exit 1
fi

if ! docker compose version &> /dev/null; then
    echo "Error: Docker Compose is not available"
    echo "Make sure Docker Desktop is installed and running"
    exit 1
fi

# Check if plugin files exist
if [ ! -f "version.php" ]; then
    echo "Warning: Plugin files not found in current directory"
    echo "Make sure you're running this from the Invigilator plugin root directory"
fi

# Make initialization scripts executable
chmod +x docker/moodle-config/01-install-plugin.sh

# Start the containers
echo "Starting containers..."
docker compose up -d

# Wait for services to be healthy
echo "Waiting for services to start..."
echo "This may take a few minutes on first run..."

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
until docker compose exec mysql mysqladmin ping -h localhost -u root -proot_password --silent; do
    echo "MySQL is starting up..."
    sleep 5
done
echo "MySQL is ready!"

# Wait for Moodle to be ready
echo "Waiting for Moodle to be ready..."
until curl -f http://localhost:8080 &> /dev/null; do
    echo "Moodle is starting up..."
    sleep 10
done
echo "Moodle is ready!"

echo ""
echo "=== Environment Ready ==="
echo "Moodle is available at: http://localhost:8080"
echo "Admin credentials:"
echo "  Username: admin"
echo "  Password: Admin123!"
echo ""
echo "MySQL is available at: localhost:3306"
echo "  Database: moodle"
echo "  Username: moodle"
echo "  Password: moodle_password"
echo ""
echo "To stop the environment: docker compose down"
echo "To view logs: docker compose logs -f"
echo "To access Moodle container: docker compose exec moodle bash"