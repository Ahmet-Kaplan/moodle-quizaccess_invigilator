#!/bin/bash

# Health check script for Moodle Invigilator test environment

set -e

echo "=== Moodle Invigilator Test Environment Health Check ==="

# Check if containers are running
echo "Checking container status..."
if ! docker compose ps | grep -q "Up"; then
    echo "❌ Containers are not running"
    echo "Run './docker/start.sh' to start the environment"
    exit 1
fi

echo "✅ Containers are running"

# Check Moodle accessibility
echo "Checking Moodle accessibility..."
if curl -f -s http://localhost:8080 > /dev/null; then
    echo "✅ Moodle is accessible at http://localhost:8080"
else
    echo "❌ Moodle is not accessible"
    echo "Check container logs: docker-compose logs moodle"
    exit 1
fi

# Check MySQL connectivity
echo "Checking MySQL connectivity..."
if docker compose exec -T mysql mysqladmin ping -h localhost -u moodle -pmoodle_password --silent; then
    echo "✅ MySQL is accessible"
else
    echo "❌ MySQL connection failed"
    echo "Check container logs: docker compose logs mysql"
    exit 1
fi

# Check plugin directory mount
echo "Checking plugin directory..."
if docker compose exec -T moodle test -d /var/www/html/mod/quiz/accessrule/invigilator; then
    echo "✅ Plugin directory is mounted"
    
    # Check if plugin files exist
    if docker compose exec -T moodle test -f /var/www/html/mod/quiz/accessrule/invigilator/version.php; then
        echo "✅ Plugin files are present"
    else
        echo "⚠️  Plugin files may be missing"
    fi
else
    echo "❌ Plugin directory not found"
    echo "Make sure you're running from the plugin root directory"
    exit 1
fi

echo ""
echo "=== Health Check Complete ==="
echo "Environment Status: ✅ Healthy"
echo ""
echo "Access URLs:"
echo "  Moodle: http://localhost:8080"
echo "  Admin: http://localhost:8080/admin"
echo ""
echo "Useful commands:"
echo "  View logs: docker compose logs -f"
echo "  Access Moodle shell: docker compose exec moodle bash"
echo "  Access MySQL shell: docker compose exec mysql mysql -u moodle -pmoodle_password moodle"