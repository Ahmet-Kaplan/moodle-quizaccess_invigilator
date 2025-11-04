#!/bin/bash

# Comprehensive Test Runner for Moodle Invigilator Plugin
# Executes all test suites and generates consolidated report

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
TEST_RESULTS_DIR="$SCRIPT_DIR/results"
TEST_LOGS_DIR="$SCRIPT_DIR/logs"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

# Test suites
TEST_SUITES=(
    "installation_test.php"
    "database_test.php"
    "integration_test.php"
    "screenshot_capture_test.php"
    "quiz_access_control_test.php"
    "admin_reporting_test.php"
)

# Initialize
log() {
    echo -e "${BLUE}[$(date +'%H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

header() {
    echo -e "${BOLD}${BLUE}$1${NC}"
}

# Setup test environment
setup_test_environment() {
    log "Setting up test environment..."
    
    # Create directories
    mkdir -p "$TEST_RESULTS_DIR"
    mkdir -p "$TEST_LOGS_DIR"
    
    # Clear previous results
    rm -f "$TEST_RESULTS_DIR"/*
    rm -f "$TEST_LOGS_DIR"/*
    
    success "Test environment ready"
}

# Check test prerequisites
check_prerequisites() {
    log "Checking test prerequisites..."
    
    # Check if we're in a Moodle environment
    if [ ! -f "$PROJECT_ROOT/../../../config.php" ]; then
        error "Not in a Moodle environment. Tests must be run from within Moodle."
        return 1
    fi
    
    # Check PHP
    if ! command -v php &> /dev/null; then
        error "PHP is not available"
        return 1
    fi
    
    # Check if test files exist
    for test_suite in "${TEST_SUITES[@]}"; do
        if [ ! -f "$SCRIPT_DIR/$test_suite" ]; then
            error "Test suite not found: $test_suite"
            return 1
        fi
    done
    
    success "Prerequisites check passed"
}

# Run individual test suite
run_test_suite() {
    local test_file="$1"
    local test_name=$(basename "$test_file" .php)
    
    header "Running $test_name..."
    
    local log_file="$TEST_LOGS_DIR/${test_name}.log"
    local result_file="$TEST_RESULTS_DIR/${test_name}.result"
    
    # Run the test
    cd "$PROJECT_ROOT"
    
    if php "$SCRIPT_DIR/$test_file" > "$log_file" 2>&1; then
        echo "PASSED" > "$result_file"
        success "$test_name completed successfully"
        return 0
    else
        echo "FAILED" > "$result_file"
        error "$test_name failed"
        
        # Show last few lines of log for immediate feedback
        echo "Last 10 lines of output:"
        tail -n 10 "$log_file" | sed 's/^/  /'
        
        return 1
    fi
}

# Run all test suites
run_all_tests() {
    log "Running all test suites..."
    
    local total_tests=${#TEST_SUITES[@]}
    local passed_tests=0
    local failed_tests=0
    local failed_suites=()
    
    for test_suite in "${TEST_SUITES[@]}"; do
        if run_test_suite "$test_suite"; then
            ((passed_tests++))
        else
            ((failed_tests++))
            failed_suites+=("$test_suite")
        fi
        echo ""
    done
    
    # Generate summary
    header "=== TEST SUMMARY ==="
    echo "Total test suites: $total_tests"
    echo -e "Passed: ${GREEN}$passed_tests${NC}"
    echo -e "Failed: ${RED}$failed_tests${NC}"
    
    if [ $failed_tests -gt 0 ]; then
        echo ""
        echo -e "${RED}Failed test suites:${NC}"
        for failed_suite in "${failed_suites[@]}"; do
            echo "  - $failed_suite"
        done
    fi
    
    return $failed_tests
}

# Generate detailed report
generate_report() {
    log "Generating detailed test report..."
    
    local report_file="$TEST_RESULTS_DIR/test_report.html"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    cat > "$report_file" << EOF
<!DOCTYPE html>
<html>
<head>
    <title>Moodle Invigilator Plugin Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f0f0f0; padding: 20px; border-radius: 5px; }
        .summary { margin: 20px 0; }
        .test-suite { margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 3px; }
        .passed { background: #d4edda; border-color: #c3e6cb; }
        .failed { background: #f8d7da; border-color: #f5c6cb; }
        .log { background: #f8f9fa; padding: 10px; font-family: monospace; font-size: 12px; overflow-x: auto; }
        pre { white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Moodle Invigilator Plugin Test Report</h1>
        <p>Generated: $timestamp</p>
    </div>
    
    <div class="summary">
        <h2>Summary</h2>
EOF

    # Add summary statistics
    local total_tests=${#TEST_SUITES[@]}
    local passed_tests=0
    local failed_tests=0
    
    for test_suite in "${TEST_SUITES[@]}"; do
        local test_name=$(basename "$test_suite" .php)
        local result_file="$TEST_RESULTS_DIR/${test_name}.result"
        
        if [ -f "$result_file" ] && [ "$(cat "$result_file")" = "PASSED" ]; then
            ((passed_tests++))
        else
            ((failed_tests++))
        fi
    done
    
    cat >> "$report_file" << EOF
        <p>Total Test Suites: $total_tests</p>
        <p>Passed: <span style="color: green;">$passed_tests</span></p>
        <p>Failed: <span style="color: red;">$failed_tests</span></p>
    </div>
    
    <div class="test-results">
        <h2>Test Results</h2>
EOF

    # Add individual test results
    for test_suite in "${TEST_SUITES[@]}"; do
        local test_name=$(basename "$test_suite" .php)
        local result_file="$TEST_RESULTS_DIR/${test_name}.result"
        local log_file="$TEST_LOGS_DIR/${test_name}.log"
        
        local status="UNKNOWN"
        local css_class="failed"
        
        if [ -f "$result_file" ]; then
            status=$(cat "$result_file")
            if [ "$status" = "PASSED" ]; then
                css_class="passed"
            fi
        fi
        
        cat >> "$report_file" << EOF
        <div class="test-suite $css_class">
            <h3>$test_name - $status</h3>
EOF

        if [ -f "$log_file" ]; then
            cat >> "$report_file" << EOF
            <div class="log">
                <h4>Output:</h4>
                <pre>$(cat "$log_file" | head -n 50)</pre>
            </div>
EOF
        fi
        
        cat >> "$report_file" << EOF
        </div>
EOF
    done
    
    cat >> "$report_file" << EOF
    </div>
</body>
</html>
EOF

    success "Test report generated: $report_file"
}

# Generate JSON report for CI/CD
generate_json_report() {
    log "Generating JSON report..."
    
    local json_file="$TEST_RESULTS_DIR/test_results.json"
    local timestamp=$(date -u +%Y-%m-%dT%H:%M:%SZ)
    
    cat > "$json_file" << EOF
{
    "timestamp": "$timestamp",
    "test_run": {
        "total_suites": ${#TEST_SUITES[@]},
        "passed": 0,
        "failed": 0,
        "suites": []
    }
}
EOF

    # Use a temporary file to build JSON
    local temp_json=$(mktemp)
    local passed_count=0
    local failed_count=0
    
    echo '{"timestamp":"'$timestamp'","test_run":{"suites":[' > "$temp_json"
    
    local first=true
    for test_suite in "${TEST_SUITES[@]}"; do
        local test_name=$(basename "$test_suite" .php)
        local result_file="$TEST_RESULTS_DIR/${test_name}.result"
        
        local status="failed"
        if [ -f "$result_file" ] && [ "$(cat "$result_file")" = "PASSED" ]; then
            status="passed"
            ((passed_count++))
        else
            ((failed_count++))
        fi
        
        if [ "$first" = false ]; then
            echo ',' >> "$temp_json"
        fi
        first=false
        
        echo -n '{"name":"'$test_name'","status":"'$status'","file":"'$test_suite'"}' >> "$temp_json"
    done
    
    echo '],"total_suites":'${#TEST_SUITES[@]}',"passed":'$passed_count',"failed":'$failed_count'}}' >> "$temp_json"
    
    mv "$temp_json" "$json_file"
    
    success "JSON report generated: $json_file"
}

# Clean up test environment
cleanup_test_environment() {
    log "Cleaning up test environment..."
    
    # Keep results and logs for review
    # Only clean up temporary files if any
    
    success "Cleanup completed"
}

# Show help
show_help() {
    cat << EOF
Moodle Invigilator Plugin Test Runner

Usage: $0 [OPTIONS]

Options:
    -h, --help          Show this help message
    -v, --verbose       Enable verbose output
    -q, --quiet         Suppress non-error output
    --no-report         Skip report generation
    --json-only         Generate only JSON report
    --suite SUITE       Run specific test suite only

Test Suites:
EOF

    for test_suite in "${TEST_SUITES[@]}"; do
        echo "    - $(basename "$test_suite" .php)"
    done
    
    cat << EOF

Examples:
    $0                          # Run all tests
    $0 --suite installation     # Run only installation tests
    $0 --json-only             # Run tests and generate JSON report only
    $0 --no-report             # Run tests without generating reports

EOF
}

# Main execution
main() {
    local verbose=false
    local quiet=false
    local no_report=false
    local json_only=false
    local specific_suite=""
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -h|--help)
                show_help
                exit 0
                ;;
            -v|--verbose)
                verbose=true
                shift
                ;;
            -q|--quiet)
                quiet=true
                shift
                ;;
            --no-report)
                no_report=true
                shift
                ;;
            --json-only)
                json_only=true
                shift
                ;;
            --suite)
                specific_suite="$2"
                shift 2
                ;;
            *)
                error "Unknown option: $1"
                show_help
                exit 1
                ;;
        esac
    done
    
    # Setup
    setup_test_environment
    check_prerequisites
    
    # Run tests
    local exit_code=0
    
    if [ -n "$specific_suite" ]; then
        # Run specific suite
        local test_file="${specific_suite}_test.php"
        if [[ " ${TEST_SUITES[@]} " =~ " ${test_file} " ]]; then
            run_test_suite "$test_file"
            exit_code=$?
        else
            error "Test suite not found: $specific_suite"
            exit_code=1
        fi
    else
        # Run all tests
        run_all_tests
        exit_code=$?
    fi
    
    # Generate reports
    if [ "$no_report" = false ]; then
        if [ "$json_only" = true ]; then
            generate_json_report
        else
            generate_report
            generate_json_report
        fi
    fi
    
    # Cleanup
    cleanup_test_environment
    
    # Final status
    if [ $exit_code -eq 0 ]; then
        success "All tests completed successfully!"
    else
        error "Some tests failed. Check the reports for details."
    fi
    
    exit $exit_code
}

# Execute main function with all arguments
main "$@"