#!/bin/bash

# Plugin Structure Validation Script
# This script validates the file structure of the Moodle Invigilator plugin

# Configuration
PLUGIN_DIR="${PLUGIN_DIR:-$(pwd)}"
PLUGIN_NAME="quizaccess_invigilator"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0

# Logging functions
log() {
    echo -e "${GREEN}[INFO] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
    ((FAILED_CHECKS++))
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

success() {
    echo -e "${GREEN}[PASS] $1${NC}"
    ((PASSED_CHECKS++))
}

info() {
    echo -e "${BLUE}[CHECK] $1${NC}"
    ((TOTAL_CHECKS++))
}

# Check if file exists
check_file_exists() {
    local file_path="$1"
    local description="$2"
    local required="${3:-true}"
    
    info "Checking $description: $file_path"
    
    if [ -f "$PLUGIN_DIR/$file_path" ]; then
        success "$description exists"
        return 0
    else
        if [ "$required" = "true" ]; then
            error "$description is missing (required)"
            return 1
        else
            warning "$description is missing (optional)"
            return 0
        fi
    fi
}

# Check if directory exists
check_directory_exists() {
    local dir_path="$1"
    local description="$2"
    local required="${3:-true}"
    
    info "Checking $description: $dir_path"
    
    if [ -d "$PLUGIN_DIR/$dir_path" ]; then
        success "$description exists"
        return 0
    else
        if [ "$required" = "true" ]; then
            error "$description is missing (required)"
            return 1
        else
            warning "$description is missing (optional)"
            return 0
        fi
    fi
}

# Validate PHP syntax
validate_php_syntax() {
    local file_path="$1"
    local description="$2"
    
    info "Validating PHP syntax: $description"
    
    if [ ! -f "$PLUGIN_DIR/$file_path" ]; then
        error "$description file not found for syntax check"
        return 1
    fi
    
    if php -l "$PLUGIN_DIR/$file_path" > /dev/null 2>&1; then
        success "$description has valid PHP syntax"
        return 0
    else
        error "$description has PHP syntax errors"
        return 1
    fi
}

# Validate version.php content
validate_version_file() {
    local version_file="$PLUGIN_DIR/version.php"
    
    info "Validating version.php content"
    
    if [ ! -f "$version_file" ]; then
        error "version.php not found"
        return 1
    fi
    
    # Check for required variables
    local required_vars=("component" "version" "requires")
    local all_found=true
    
    for var in "${required_vars[@]}"; do
        if grep -q "\$plugin->$var" "$version_file"; then
            success "Found required variable: \$plugin->$var"
        else
            error "Missing required variable: \$plugin->$var"
            all_found=false
        fi
    done
    
    # Check component name
    if grep -q "quizaccess_invigilator" "$version_file"; then
        success "Component name is correct"
    else
        error "Component name is incorrect or missing"
        all_found=false
    fi
    
    if [ "$all_found" = true ]; then
        return 0
    else
        return 1
    fi
}

# Validate database schema
validate_database_schema() {
    local install_xml="$PLUGIN_DIR/db/install.xml"
    
    info "Validating database schema"
    
    if [ ! -f "$install_xml" ]; then
        error "db/install.xml not found"
        return 1
    fi
    
    # Check XML syntax
    if xmllint --noout "$install_xml" 2>/dev/null; then
        success "install.xml has valid XML syntax"
    else
        error "install.xml has invalid XML syntax"
        return 1
    fi
    
    # Check for required elements
    if grep -q "<XMLDB" "$install_xml"; then
        success "install.xml contains XMLDB structure"
    else
        error "install.xml missing XMLDB structure"
        return 1
    fi
    
    return 0
}

# Validate language files
validate_language_files() {
    local lang_file="$PLUGIN_DIR/lang/en/quizaccess_invigilator.php"
    
    info "Validating language files"
    
    if [ ! -f "$lang_file" ]; then
        error "English language file not found"
        return 1
    fi
    
    # Check PHP syntax
    if php -l "$lang_file" > /dev/null 2>&1; then
        success "Language file has valid PHP syntax"
    else
        error "Language file has PHP syntax errors"
        return 1
    fi
    
    # Check for plugin name string
    if grep -q "pluginname" "$lang_file"; then
        success "Language file contains pluginname string"
    else
        error "Language file missing pluginname string"
        return 1
    fi
    
    return 0
}

# Main validation function
validate_plugin_structure() {
    log "Starting plugin structure validation for $PLUGIN_NAME"
    log "Plugin directory: $PLUGIN_DIR"
    echo ""
    
    # Reset counters
    TOTAL_CHECKS=0
    PASSED_CHECKS=0
    FAILED_CHECKS=0
    
    # Core files validation
    echo -e "${BLUE}=== Core Files ===${NC}"
    check_file_exists "version.php" "Version file"
    check_file_exists "rule.php" "Main rule file"
    check_file_exists "lib.php" "Library file" false
    check_file_exists "settings.php" "Settings file" false
    
    # Directory structure validation
    echo -e "\n${BLUE}=== Directory Structure ===${NC}"
    check_directory_exists "classes" "Classes directory"
    check_directory_exists "db" "Database directory"
    check_directory_exists "lang" "Language directory"
    check_directory_exists "lang/en" "English language directory"
    check_directory_exists "amd" "AMD directory" false
    check_directory_exists "pix" "Pictures directory" false
    
    # Database files
    echo -e "\n${BLUE}=== Database Files ===${NC}"
    check_file_exists "db/install.xml" "Database install schema"
    check_file_exists "db/access.php" "Access definitions"
    check_file_exists "db/services.php" "Web services definitions" false
    
    # Language files
    echo -e "\n${BLUE}=== Language Files ===${NC}"
    check_file_exists "lang/en/quizaccess_invigilator.php" "English language strings"
    
    # Class files
    echo -e "\n${BLUE}=== Class Files ===${NC}"
    check_file_exists "classes/external.php" "External API class" false
    check_file_exists "classes/privacy/provider.php" "Privacy provider class" false
    
    # AMD/JavaScript files
    echo -e "\n${BLUE}=== JavaScript Files ===${NC}"
    check_file_exists "amd/src/additionalSettings.js" "Additional settings JS" false
    check_file_exists "amd/src/lightbox2.js" "Lightbox JS" false
    
    # PHP syntax validation
    echo -e "\n${BLUE}=== PHP Syntax Validation ===${NC}"
    validate_php_syntax "version.php" "Version file" || true
    validate_php_syntax "rule.php" "Rule file" || true
    [ -f "$PLUGIN_DIR/lib.php" ] && validate_php_syntax "lib.php" "Library file" || true
    [ -f "$PLUGIN_DIR/settings.php" ] && validate_php_syntax "settings.php" "Settings file" || true
    
    # Content validation
    echo -e "\n${BLUE}=== Content Validation ===${NC}"
    validate_version_file || true
    validate_database_schema || true
    validate_language_files || true
    
    # Summary
    echo -e "\n${BLUE}=== Validation Summary ===${NC}"
    log "Total checks performed: $TOTAL_CHECKS"
    log "Checks passed: $PASSED_CHECKS"
    log "Checks failed: $FAILED_CHECKS"
    
    if [ $FAILED_CHECKS -eq 0 ]; then
        echo -e "${GREEN}✓ Plugin structure validation PASSED${NC}"
        return 0
    else
        echo -e "${RED}✗ Plugin structure validation FAILED${NC}"
        echo -e "${RED}Please fix the issues above before proceeding with installation${NC}"
        return 1
    fi
}

# Handle command line arguments
case "${1:-validate}" in
    "validate")
        validate_plugin_structure
        ;;
    "help"|"-h"|"--help")
        echo "Usage: $0 [validate|help]"
        echo "  validate - Validate plugin structure (default)"
        echo "  help     - Show this help message"
        echo ""
        echo "Environment variables:"
        echo "  PLUGIN_DIR - Path to plugin directory (default: current directory)"
        ;;
    *)
        error "Unknown command: $1"
        echo "Use '$0 help' for usage information"
        exit 1
        ;;
esac