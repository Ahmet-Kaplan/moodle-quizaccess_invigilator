#!/bin/bash

# Plugin Installation Script for Moodle Invigilator Plugin
# This script copies the plugin files to the correct Moodle directory structure

set -e  # Exit on any error

# Configuration
PLUGIN_NAME="quizaccess_invigilator"
PLUGIN_TYPE="mod/quiz/accessrule"
MOODLE_ROOT="${MOODLE_ROOT:-/var/www/html}"
PLUGIN_SOURCE_DIR="${PLUGIN_SOURCE_DIR:-$(pwd)}"
PLUGIN_TARGET_DIR="${MOODLE_ROOT}/${PLUGIN_TYPE}/invigilator"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}" >&2
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

# Validate environment
validate_environment() {
    log "Validating environment..."
    
    if [ ! -d "$MOODLE_ROOT" ]; then
        error "Moodle root directory not found: $MOODLE_ROOT"
        exit 1
    fi
    
    if [ ! -f "$MOODLE_ROOT/config.php" ]; then
        error "Moodle config.php not found. Is this a valid Moodle installation?"
        exit 1
    fi
    
    if [ ! -d "$PLUGIN_SOURCE_DIR" ]; then
        error "Plugin source directory not found: $PLUGIN_SOURCE_DIR"
        exit 1
    fi
    
    if [ ! -f "$PLUGIN_SOURCE_DIR/version.php" ]; then
        error "Plugin version.php not found in source directory"
        exit 1
    fi
    
    log "Environment validation passed"
}

# Create target directory structure
create_target_directory() {
    log "Creating target directory: $PLUGIN_TARGET_DIR"
    
    # Create the target directory if it doesn't exist
    mkdir -p "$PLUGIN_TARGET_DIR"
    
    # Set proper permissions
    chmod 755 "$PLUGIN_TARGET_DIR"
    
    log "Target directory created successfully"
}

# Copy plugin files
copy_plugin_files() {
    log "Copying plugin files from $PLUGIN_SOURCE_DIR to $PLUGIN_TARGET_DIR"
    
    # List of files and directories to copy
    local files_to_copy=(
        "version.php"
        "rule.php"
        "lib.php"
        "settings.php"
        "styles.css"
        "thirdpartylibs.xml"
        "additional_settings.php"
        "bulkdelete.php"
        "invigilatorsummary.php"
        "report.php"
        "classes/"
        "db/"
        "lang/"
        "amd/"
        "pix/"
    )
    
    for item in "${files_to_copy[@]}"; do
        local source_path="$PLUGIN_SOURCE_DIR/$item"
        local target_path="$PLUGIN_TARGET_DIR/$item"
        
        if [ -e "$source_path" ]; then
            if [ -d "$source_path" ]; then
                log "Copying directory: $item"
                cp -r "$source_path" "$target_path"
            else
                log "Copying file: $item"
                cp "$source_path" "$target_path"
            fi
        else
            warning "Source file/directory not found: $item"
        fi
    done
    
    log "Plugin files copied successfully"
}

# Set proper file permissions
set_permissions() {
    log "Setting proper file permissions..."
    
    # Set directory permissions
    find "$PLUGIN_TARGET_DIR" -type d -exec chmod 755 {} \;
    
    # Set file permissions
    find "$PLUGIN_TARGET_DIR" -type f -exec chmod 644 {} \;
    
    # Make PHP files executable if needed
    find "$PLUGIN_TARGET_DIR" -name "*.php" -exec chmod 644 {} \;
    
    log "File permissions set successfully"
}

# Verify installation
verify_installation() {
    log "Verifying plugin installation..."
    
    # Check if essential files exist
    local essential_files=(
        "version.php"
        "rule.php"
        "db/install.xml"
        "lang/en/quizaccess_invigilator.php"
    )
    
    for file in "${essential_files[@]}"; do
        if [ ! -f "$PLUGIN_TARGET_DIR/$file" ]; then
            error "Essential file missing: $file"
            exit 1
        fi
    done
    
    # Validate version.php syntax
    if ! php -l "$PLUGIN_TARGET_DIR/version.php" > /dev/null 2>&1; then
        error "Syntax error in version.php"
        exit 1
    fi
    
    log "Plugin installation verification passed"
}

# Main installation function
install_plugin() {
    log "Starting plugin installation for $PLUGIN_NAME"
    
    validate_environment
    create_target_directory
    copy_plugin_files
    set_permissions
    verify_installation
    
    log "Plugin installation completed successfully!"
    log "Plugin installed at: $PLUGIN_TARGET_DIR"
    log "Next steps:"
    log "1. Access Moodle admin interface"
    log "2. Navigate to Site administration > Notifications"
    log "3. Complete the plugin installation process"
}

# Handle command line arguments
case "${1:-install}" in
    "install")
        install_plugin
        ;;
    "verify")
        verify_installation
        ;;
    "help"|"-h"|"--help")
        echo "Usage: $0 [install|verify|help]"
        echo "  install  - Install the plugin (default)"
        echo "  verify   - Verify existing installation"
        echo "  help     - Show this help message"
        echo ""
        echo "Environment variables:"
        echo "  MOODLE_ROOT        - Path to Moodle installation (default: /var/www/html)"
        echo "  PLUGIN_SOURCE_DIR  - Path to plugin source files (default: current directory)"
        ;;
    *)
        error "Unknown command: $1"
        echo "Use '$0 help' for usage information"
        exit 1
        ;;
esac