#!/bin/bash

# Comprehensive Plugin Installation Wrapper Script
# This script orchestrates the complete plugin installation process

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(dirname "$SCRIPT_DIR")"
MOODLE_ROOT="${MOODLE_ROOT:-/var/www/html}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}" >&2
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

# Show usage information
show_usage() {
    cat << EOF
Moodle Invigilator Plugin Installation Script

Usage: $0 [OPTIONS] COMMAND

Commands:
    validate    - Validate plugin structure before installation
    install     - Install plugin files and run Moodle installation
    upgrade     - Upgrade existing plugin installation
    check       - Check current plugin status
    uninstall   - Remove plugin files (use with caution)
    help        - Show this help message

Options:
    -v, --verbose           Enable verbose output
    -n, --non-interactive   Run in non-interactive mode
    -f, --force            Force installation even if validation fails
    --moodle-root PATH     Specify Moodle root directory (default: /var/www/html)
    --plugin-dir PATH      Specify plugin source directory (default: parent of script dir)

Environment Variables:
    MOODLE_ROOT            Path to Moodle installation
    PLUGIN_SOURCE_DIR      Path to plugin source files

Examples:
    $0 validate                           # Validate plugin structure
    $0 install --verbose                  # Install with verbose output
    $0 upgrade --non-interactive          # Upgrade without prompts
    $0 --moodle-root /opt/moodle install  # Install to custom Moodle path

EOF
}

# Parse command line arguments
parse_arguments() {
    VERBOSE=false
    NON_INTERACTIVE=false
    FORCE=false
    COMMAND=""
    
    while [[ $# -gt 0 ]]; do
        case $1 in
            -v|--verbose)
                VERBOSE=true
                shift
                ;;
            -n|--non-interactive)
                NON_INTERACTIVE=true
                shift
                ;;
            -f|--force)
                FORCE=true
                shift
                ;;
            --moodle-root)
                MOODLE_ROOT="$2"
                shift 2
                ;;
            --plugin-dir)
                PLUGIN_ROOT="$2"
                shift 2
                ;;
            validate|install|upgrade|check|uninstall|help)
                if [ -n "$COMMAND" ]; then
                    error "Multiple commands specified. Use only one command."
                    exit 1
                fi
                COMMAND="$1"
                shift
                ;;
            -h|--help)
                COMMAND="help"
                shift
                ;;
            *)
                error "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done
    
    # Default command
    if [ -z "$COMMAND" ]; then
        COMMAND="help"
    fi
    
    # Export environment variables
    export MOODLE_ROOT
    export PLUGIN_SOURCE_DIR="$PLUGIN_ROOT"
}

# Validate plugin structure
validate_plugin() {
    log "Validating plugin structure..."
    
    local validation_script="$SCRIPT_DIR/validate-plugin-structure.sh"
    
    if [ ! -f "$validation_script" ]; then
        error "Validation script not found: $validation_script"
        return 1
    fi
    
    # Make script executable
    chmod +x "$validation_script"
    
    # Run validation
    if PLUGIN_DIR="$PLUGIN_ROOT" "$validation_script" validate; then
        log "Plugin structure validation passed"
        return 0
    else
        error "Plugin structure validation failed"
        return 1
    fi
}

# Install plugin files
install_plugin_files() {
    log "Installing plugin files..."
    
    local install_script="$SCRIPT_DIR/install-plugin.sh"
    
    if [ ! -f "$install_script" ]; then
        error "Installation script not found: $install_script"
        return 1
    fi
    
    # Make script executable
    chmod +x "$install_script"
    
    # Run installation
    if "$install_script" install; then
        log "Plugin files installed successfully"
        return 0
    else
        error "Plugin file installation failed"
        return 1
    fi
}

# Run Moodle CLI installation
run_moodle_cli() {
    local action="$1"
    log "Running Moodle CLI $action..."
    
    local cli_script="$SCRIPT_DIR/moodle-cli-install.php"
    
    if [ ! -f "$cli_script" ]; then
        error "Moodle CLI script not found: $cli_script"
        return 1
    fi
    
    # Prepare CLI arguments
    local cli_args=""
    
    case "$action" in
        "install")
            cli_args="--install"
            ;;
        "upgrade")
            cli_args="--upgrade"
            ;;
        "check")
            cli_args="--check"
            ;;
        *)
            error "Unknown CLI action: $action"
            return 1
            ;;
    esac
    
    if [ "$VERBOSE" = true ]; then
        cli_args="$cli_args --verbose"
    fi
    
    if [ "$NON_INTERACTIVE" = true ]; then
        cli_args="$cli_args --non-interactive"
    fi
    
    # Run CLI script
    if php "$cli_script" $cli_args; then
        log "Moodle CLI $action completed successfully"
        return 0
    else
        error "Moodle CLI $action failed"
        return 1
    fi
}

# Full installation process
install_plugin() {
    log "Starting full plugin installation process..."
    
    # Step 1: Validate structure (unless forced)
    if [ "$FORCE" != true ]; then
        if ! validate_plugin; then
            error "Plugin validation failed. Use --force to skip validation."
            return 1
        fi
    else
        warning "Skipping validation due to --force flag"
    fi
    
    # Step 2: Install plugin files
    if ! install_plugin_files; then
        error "Failed to install plugin files"
        return 1
    fi
    
    # Step 3: Run Moodle CLI installation
    if ! run_moodle_cli "install"; then
        error "Failed to complete Moodle installation"
        return 1
    fi
    
    log "Plugin installation completed successfully!"
    log "Next steps:"
    log "1. Access your Moodle site as an administrator"
    log "2. Navigate to Site administration > Notifications"
    log "3. Complete any remaining installation steps"
    
    return 0
}

# Upgrade plugin
upgrade_plugin() {
    log "Starting plugin upgrade process..."
    
    # Step 1: Validate structure (unless forced)
    if [ "$FORCE" != true ]; then
        if ! validate_plugin; then
            error "Plugin validation failed. Use --force to skip validation."
            return 1
        fi
    fi
    
    # Step 2: Install/update plugin files
    if ! install_plugin_files; then
        error "Failed to update plugin files"
        return 1
    fi
    
    # Step 3: Run Moodle CLI upgrade
    if ! run_moodle_cli "upgrade"; then
        error "Failed to complete Moodle upgrade"
        return 1
    fi
    
    log "Plugin upgrade completed successfully!"
    return 0
}

# Check plugin status
check_plugin() {
    log "Checking plugin status..."
    
    # Check file installation
    local plugin_target="$MOODLE_ROOT/mod/quiz/accessrule/invigilator"
    
    if [ -d "$plugin_target" ]; then
        log "✓ Plugin files are installed at: $plugin_target"
    else
        warning "✗ Plugin files not found at: $plugin_target"
    fi
    
    # Run Moodle CLI check
    run_moodle_cli "check"
}

# Remove plugin (use with caution)
uninstall_plugin() {
    warning "This will remove the plugin files from Moodle!"
    
    if [ "$NON_INTERACTIVE" != true ]; then
        echo -n "Are you sure you want to uninstall the plugin? (y/N): "
        read -r response
        if [[ ! "$response" =~ ^[Yy]$ ]]; then
            log "Uninstall cancelled"
            return 0
        fi
    fi
    
    local plugin_target="$MOODLE_ROOT/mod/quiz/accessrule/invigilator"
    
    if [ -d "$plugin_target" ]; then
        log "Removing plugin files from: $plugin_target"
        rm -rf "$plugin_target"
        log "Plugin files removed successfully"
        log "Note: You may need to manually remove database entries through Moodle admin interface"
    else
        warning "Plugin directory not found: $plugin_target"
    fi
}

# Main execution
main() {
    parse_arguments "$@"
    
    # Show configuration if verbose
    if [ "$VERBOSE" = true ]; then
        info "Configuration:"
        info "  Moodle Root: $MOODLE_ROOT"
        info "  Plugin Source: $PLUGIN_ROOT"
        info "  Script Directory: $SCRIPT_DIR"
        info "  Command: $COMMAND"
        echo ""
    fi
    
    # Execute command
    case "$COMMAND" in
        "validate")
            validate_plugin
            ;;
        "install")
            install_plugin
            ;;
        "upgrade")
            upgrade_plugin
            ;;
        "check")
            check_plugin
            ;;
        "uninstall")
            uninstall_plugin
            ;;
        "help")
            show_usage
            ;;
        *)
            error "Unknown command: $COMMAND"
            show_usage
            exit 1
            ;;
    esac
}

# Run main function with all arguments
main "$@"