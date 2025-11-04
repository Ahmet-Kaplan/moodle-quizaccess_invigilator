#!/bin/bash

# Moodle Invigilator Plugin Installation Script
# This script runs during container initialization to install the plugin

set -e

echo "Starting Invigilator plugin installation..."

# Wait for Moodle to be fully initialized
while [ ! -f /var/www/html/config.php ]; do
    echo "Waiting for Moodle to initialize..."
    sleep 5
done

# Check if plugin is already installed
if [ -d "/var/www/html/mod/quiz/accessrule/invigilator" ]; then
    echo "Invigilator plugin directory found, checking installation status..."
    
    # Run Moodle CLI to check and install plugins
    cd /var/www/html
    
    # Check for pending plugin installations
    php admin/cli/upgrade.php --non-interactive
    
    echo "Plugin installation check completed."
else
    echo "Warning: Invigilator plugin directory not found at /var/www/html/mod/quiz/accessrule/invigilator"
    echo "Make sure the plugin files are properly mounted."
fi

echo "Invigilator plugin installation script completed."