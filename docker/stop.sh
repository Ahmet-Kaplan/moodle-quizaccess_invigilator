#!/bin/bash

# Moodle Invigilator Test Environment Stop Script

set -e

echo "=== Stopping Moodle Invigilator Test Environment ==="

# Stop and remove containers
echo "Stopping containers..."
docker compose down

echo "Containers stopped."
echo ""
echo "To remove all data (including database): docker compose down -v"
echo "To start again: ./docker/start.sh"