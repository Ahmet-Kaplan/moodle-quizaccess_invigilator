#!/bin/bash

# Comprehensive Plugin Compatibility Validation Script
# This script runs all validation checks for Moodle 4.4.3 compatibility

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
Comprehensive Plugin Compatibility Validation Script

Usage: $0 [OPTIONS] [COMMAND]

Commands:
    all             - Run all validation checks (default)
    structure       - Validate plugin file structure only
    compatibility   - Run Moodle 4.4.3 compatibility checks only
    database        - Run database schema validation only
    activation      - Run plugin activation verification only
    help            - Show this help message

Options:
    -v, --verbose           Enable verbose output
    --moodle-root PATH     Specify Moodle root directory (default: /var/www/html)
    --plugin-dir PATH      Specify plugin source directory (default: parent of script dir)
    --continue-on-error    Continue validation even if some checks fail
    --report-file PATH     Save detailed report to file

Environment Variables:
    MOODLE_ROOT            Path to Moodle installation
    PLUGIN_SOURCE_DIR      Path to plugin source files

Examples:
    $0                                    # Run all validations
    $0 structure --verbose                # Validate structure with verbose output
    $0 compatibility --report-file report.txt  # Run compatibility check and save report
    $0 --moodle-root /opt/moodle all     # Run all checks with custom Moodle path

EOF
}

# Parse command line arguments
parse_arguments() {
    VERBOSE=false
    CONTINUE_ON_ERROR=false
    REPORT_FILE=""
    COMMAND="all"
    
    while [[ $# -gt 0 ]]; do
        case $1 in
            -v|--verbose)
                VERBOSE=true
                shift
                ;;
            --continue-on-error)
                CONTINUE_ON_ERROR=true
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
            --report-file)
                REPORT_FILE="$2"
                shift 2
                ;;
            all|structure|compatibility|database|activation|help)
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
    
    # Export environment variables
    export MOODLE_ROOT
    export PLUGIN_SOURCE_DIR="$PLUGIN_ROOT"
}

# Initialize report file
init_report() {
    if [ -n "$REPORT_FILE" ]; then
        cat > "$REPORT_FILE" << EOF
# Plugin Compatibility Validation Report
Generated: $(date)
Plugin Directory: $PLUGIN_ROOT
Moodle Root: $MOODLE_ROOT

EOF
        log "Report will be saved to: $REPORT_FILE"
    fi
}

# Add to report
add_to_report() {
    local section="$1"
    local content="$2"
    
    if [ -n "$REPORT_FILE" ]; then
        cat >> "$REPORT_FILE" << EOF

## $section

$content

EOF
    fi
}

# Run script and capture output
run_script() {
    local script_name="$1"
    local description="$2"
    local args="$3"
    
    local script_path="$SCRIPT_DIR/$script_name"
    
    if [ ! -f "$script_path" ]; then
        error "Script not found: $script_path"
        return 1
    fi
    
    log "Running $description..."
    
    # Prepare arguments
    local script_args=""
    if [ "$VERBOSE" = true ]; then
        script_args="$script_args --verbose"
    fi
    
    if [ -n "$args" ]; then
        script_args="$script_args $args"
    fi
    
    # Run script and capture output
    local output
    local exit_code
    
    if output=$(bash "$script_path" $script_args 2>&1); then
        exit_code=0
        log "✓ $description completed successfully"
    else
        exit_code=$?
        error "✗ $description failed (exit code: $exit_code)"
    fi
    
    # Add to report if enabled
    if [ -n "$REPORT_FILE" ]; then
        add_to_report "$description" "$output"
    fi
    
    # Show output if verbose or if failed
    if [ "$VERBOSE" = true ] || [ $exit_code -ne 0 ]; then
        echo "$output"
    fi
    
    return $exit_code
}

# Run PHP script and capture output
run_php_script() {
    local script_name="$1"
    local description="$2"
    local args="$3"
    
    local script_path="$SCRIPT_DIR/$script_name"
    
    if [ ! -f "$script_path" ]; then
        error "Script not found: $script_path"
        return 1
    fi
    
    log "Running $description..."
    
    # Prepare arguments
    local script_args=""
    if [ "$VERBOSE" = true ]; then
        script_args="$script_args --verbose"
    fi
    
    if [ -n "$args" ]; then
        script_args="$script_args $args"
    fi
    
    # Run PHP script and capture output
    local output
    local exit_code
    
    if output=$(php "$script_path" $script_args 2>&1); then
        exit_code=0
        log "✓ $description completed successfully"
    else
        exit_code=$?
        error "✗ $description failed (exit code: $exit_code)"
    fi
    
    # Add to report if enabled
    if [ -n "$REPORT_FILE" ]; then
        add_to_report "$description" "$output"
    fi
    
    # Show output if verbose or if failed
    if [ "$VERBOSE" = true ] || [ $exit_code -ne 0 ]; then
        echo "$output"
    fi
    
    return $exit_code
}

# Validate plugin structure
validate_structure() {
    run_script "validate-plugin-structure.sh" "Plugin Structure Validation" "validate"
}

# Run compatibility checks
validate_compatibility() {
    run_php_script "compatibility-validator.php" "Moodle 4.4.3 Compatibility Check" "--check-all"
}

# Run database validation
validate_database() {
    run_php_script "database-validator.php" "Database Schema Validation" "--validate-schema --check-tables"
}

# Run activation verification
validate_activation() {
    run_php_script "activation-validator.php" "Plugin Activation Verification" "--check-installation --check-capabilities --check-integration"
}

# Run all validations
validate_all() {
    log "Starting comprehensive plugin validation..."
    
    local failed_checks=0
    local total_checks=4
    
    # Structure validation
    if ! validate_structure; then
        ((failed_checks++))
        if [ "$CONTINUE_ON_ERROR" != true ]; then
            error "Structure validation failed. Use --continue-on-error to continue."
            return 1
        fi
    fi
    
    # Compatibility validation
    if ! validate_compatibility; then
        ((failed_checks++))
        if [ "$CONTINUE_ON_ERROR" != true ]; then
            error "Compatibility validation failed. Use --continue-on-error to continue."
            return 1
        fi
    fi
    
    # Database validation
    if ! validate_database; then
        ((failed_checks++))
        if [ "$CONTINUE_ON_ERROR" != true ]; then
            error "Database validation failed. Use --continue-on-error to continue."
            return 1
        fi
    fi
    
    # Activation validation (only if Moodle is available)
    if [ -f "$MOODLE_ROOT/config.php" ]; then
        if ! validate_activation; then
            ((failed_checks++))
            if [ "$CONTINUE_ON_ERROR" != true ]; then
                error "Activation validation failed. Use --continue-on-error to continue."
                return 1
            fi
        fi
    else
        warning "Moodle not found at $MOODLE_ROOT, skipping activation validation"
        ((total_checks--))
    fi
    
    # Summary
    local passed_checks=$((total_checks - failed_checks))
    
    log ""
    log "=== COMPREHENSIVE VALIDATION SUMMARY ==="
    log "Total checks: $total_checks"
    log "Passed: $passed_checks"
    log "Failed: $failed_checks"
    
    if [ $failed_checks -eq 0 ]; then
        log "✓ All validations PASSED - Plugin is ready for Moodle 4.4.3"
        add_to_report "Summary" "✓ All validations PASSED - Plugin is ready for Moodle 4.4.3"
        return 0
    else
        error "✗ $failed_checks validation(s) FAILED - Plugin needs fixes before deployment"
        add_to_report "Summary" "✗ $failed_checks validation(s) FAILED - Plugin needs fixes before deployment"
        return 1
    fi
}

# Main execution
main() {
    parse_arguments "$@"
    
    # Show configuration if verbose
    if [ "$VERBOSE" = true ]; then
        info "Configuration:"
        info "  Moodle Root: $MOODLE_ROOT"
        info "  Plugin Directory: $PLUGIN_ROOT"
        info "  Script Directory: $SCRIPT_DIR"
        info "  Command: $COMMAND"
        info "  Continue on Error: $CONTINUE_ON_ERROR"
        info "  Report File: ${REPORT_FILE:-'None'}"
        echo ""
    fi
    
    # Initialize report
    init_report
    
    # Execute command
    case "$COMMAND" in
        "structure")
            validate_structure
            ;;
        "compatibility")
            validate_compatibility
            ;;
        "database")
            validate_database
            ;;
        "activation")
            validate_activation
            ;;
        "all")
            validate_all
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
    
    local exit_code=$?
    
    if [ -n "$REPORT_FILE" ]; then
        log "Detailed report saved to: $REPORT_FILE"
    fi
    
    exit $exit_code
}

# Run main function with all arguments
main "$@"