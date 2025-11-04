#!/bin/bash

# Docker Test Orchestration Script for Moodle Invigilator Plugin
# This script manages the complete test environment lifecycle

set -e

# Configuration
TEST_COMPOSE_FILE="tests/docker-compose.test.yml"
TEST_ENV_FILE="tests/.env.test"
TEST_DATA_DIR="tests/data"
TEST_RESULTS_DIR="tests/results"
CONTAINER_PREFIX="moodle-invigilator-test"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
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

# Help function
show_help() {
    cat << EOF
Docker Test Orchestration for Moodle Invigilator Plugin

Usage: $0 [COMMAND] [OPTIONS]

Commands:
    setup       Set up test environment
    start       Start test containers
    stop        Stop test containers
    clean       Clean up test environment
    test        Run complete test suite
    install     Install plugin in test environment
    reset       Reset test environment to clean state
    logs        Show container logs
    shell       Access test container shell
    status      Show container status

Options:
    -h, --help      Show this help message
    -v, --verbose   Enable verbose output
    -q, --quiet     Suppress non-error output
    --no-cleanup    Skip cleanup after tests
    --keep-data     Keep test data after cleanup

Examples:
    $0 setup                    # Set up test environment
    $0 test                     # Run complete test suite
    $0 test --no-cleanup        # Run tests without cleanup
    $0 shell                    # Access Moodle container
    $0 clean                    # Clean up everything

EOF
}

# Check dependencies
check_dependencies() {
    log "Checking dependencies..."
    
    if ! command -v docker &> /dev/null; then
        error "Docker is not installed or not in PATH"
        exit 1
    fi
    
    if ! docker compose version &> /dev/null; then
        error "Docker Compose is not available"
        exit 1
    fi
    
    success "Dependencies check passed"
}

# Create test directories
create_test_directories() {
    log "Creating test directories..."
    
    mkdir -p "$TEST_DATA_DIR"
    mkdir -p "$TEST_RESULTS_DIR"
    mkdir -p "tests/fixtures"
    mkdir -p "tests/logs"
    
    success "Test directories created"
}

# Generate test environment configuration
generate_test_config() {
    log "Generating test configuration..."
    
    cat > "$TEST_ENV_FILE" << EOF
# Test Environment Configuration
MOODLE_DATABASE_TYPE=mysqli
MOODLE_DATABASE_HOST=mysql-test
MOODLE_DATABASE_NAME=moodle_test
MOODLE_DATABASE_USER=moodle_test
MOODLE_DATABASE_PASSWORD=test_password_123
MOODLE_DATABASE_PORT=3306
MOODLE_ADMIN_USER=testadmin
MOODLE_ADMIN_PASSWORD=TestAdmin123!
MOODLE_ADMIN_EMAIL=testadmin@example.com
MOODLE_SITE_NAME=Moodle Invigilator Test
MOODLE_SITE_SHORTNAME=invigilator-test
MOODLE_WWWROOT=http://localhost:8081
MOODLE_DEBUG=15
MOODLE_DEBUG_DISPLAY=1
EOF
    
    success "Test configuration generated"
}

# Set up test environment
setup_environment() {
    log "Setting up test environment..."
    
    check_dependencies
    create_test_directories
    generate_test_config
    create_test_compose_file
    create_test_data_scripts
    
    success "Test environment setup complete"
}

# Create test-specific docker-compose file
create_test_compose_file() {
    log "Creating test Docker Compose configuration..."
    
    cat > "$TEST_COMPOSE_FILE" << EOF
services:
  moodle-test:
    image: moodle:4.4.3-apache
    container_name: ${CONTAINER_PREFIX}-moodle
    ports:
      - "8081:80"
    env_file:
      - .env.test
    volumes:
      - moodle_test_data:/var/www/moodledata
      - ../:/var/www/html/mod/quiz/accessrule/invigilator:ro
      - ./fixtures:/var/www/html/test-fixtures:ro
      - ./scripts:/test-scripts:ro
    depends_on:
      mysql-test:
        condition: service_healthy
    networks:
      - test_network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 5
      start_period: 60s

  mysql-test:
    image: mysql:8.0
    container_name: ${CONTAINER_PREFIX}-mysql
    environment:
      - MYSQL_ROOT_PASSWORD=root_test_password
      - MYSQL_DATABASE=moodle_test
      - MYSQL_USER=moodle_test
      - MYSQL_PASSWORD=test_password_123
    volumes:
      - mysql_test_data:/var/lib/mysql
      - ./sql:/docker-entrypoint-initdb.d:ro
    ports:
      - "3307:3306"
    networks:
      - test_network
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-proot_test_password"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    command: --default-authentication-plugin=mysql_native_password

volumes:
  moodle_test_data:
    driver: local
  mysql_test_data:
    driver: local

networks:
  test_network:
    driver: bridge
EOF
    
    success "Test Docker Compose configuration created"
}

# Create test data initialization scripts
create_test_data_scripts() {
    log "Creating test data initialization scripts..."
    
    mkdir -p "tests/scripts"
    
    # Plugin installation script for tests
    cat > "tests/scripts/install-plugin.sh" << 'EOF'
#!/bin/bash
set -e

echo "Installing Invigilator plugin for testing..."

# Wait for Moodle to be ready
until curl -f http://localhost/ &> /dev/null; do
    echo "Waiting for Moodle to be ready..."
    sleep 5
done

# Install plugin via CLI
cd /var/www/html
php admin/cli/upgrade.php --non-interactive

# Verify plugin installation
php admin/cli/cfg.php --name=version

echo "Plugin installation complete"
EOF

    # Test data creation script
    cat > "tests/scripts/create-test-data.sh" << 'EOF'
#!/bin/bash
set -e

echo "Creating test data..."

cd /var/www/html

# Create test course
php admin/cli/cfg.php --shell << 'PHPEOF'
require_once('config.php');
require_once($CFG->dirroot . '/course/lib.php');

// Create test course
$coursedata = new stdClass();
$coursedata->fullname = 'Invigilator Test Course';
$coursedata->shortname = 'INVTEST';
$coursedata->category = 1;
$coursedata->summary = 'Course for testing Invigilator plugin';
$coursedata->format = 'topics';

$course = create_course($coursedata);
echo "Created course with ID: " . $course->id . "\n";

// Create test users
$user1 = new stdClass();
$user1->username = 'teststudent1';
$user1->password = 'TestStudent123!';
$user1->firstname = 'Test';
$user1->lastname = 'Student1';
$user1->email = 'teststudent1@example.com';
$user1->auth = 'manual';
$user1->confirmed = 1;
$user1->mnethostid = 1;

$userid1 = user_create_user($user1);
echo "Created user with ID: " . $userid1 . "\n";

// Create test quiz
require_once($CFG->dirroot . '/mod/quiz/lib.php');

$quiz = new stdClass();
$quiz->course = $course->id;
$quiz->name = 'Invigilator Test Quiz';
$quiz->intro = 'Quiz for testing screenshot capture';
$quiz->timeopen = 0;
$quiz->timeclose = 0;
$quiz->timelimit = 0;
$quiz->attempts = 0;

$quiz->id = quiz_add_instance($quiz);
echo "Created quiz with ID: " . $quiz->id . "\n";

echo "Test data creation complete";
PHPEOF

echo "Test data created successfully"
EOF

    chmod +x "tests/scripts/install-plugin.sh"
    chmod +x "tests/scripts/create-test-data.sh"
    
    success "Test data scripts created"
}

# Start test containers
start_containers() {
    log "Starting test containers..."
    
    cd tests
    docker compose -f docker-compose.test.yml up -d
    
    log "Waiting for containers to be healthy..."
    
    # Wait for MySQL
    until docker compose -f docker-compose.test.yml exec mysql-test mysqladmin ping -h localhost -u root -proot_test_password --silent; do
        log "Waiting for MySQL..."
        sleep 5
    done
    
    # Wait for Moodle
    until curl -f http://localhost:8081 &> /dev/null; do
        log "Waiting for Moodle..."
        sleep 10
    done
    
    success "Test containers are running"
    cd ..
}

# Stop test containers
stop_containers() {
    log "Stopping test containers..."
    
    cd tests
    docker compose -f docker-compose.test.yml down
    cd ..
    
    success "Test containers stopped"
}

# Install plugin in test environment
install_plugin() {
    log "Installing plugin in test environment..."
    
    cd tests
    docker compose -f docker-compose.test.yml exec moodle-test /test-scripts/install-plugin.sh
    cd ..
    
    success "Plugin installed"
}

# Create test data
create_test_data() {
    log "Creating test data..."
    
    cd tests
    docker compose -f docker-compose.test.yml exec moodle-test /test-scripts/create-test-data.sh
    cd ..
    
    success "Test data created"
}

# Run complete test suite
run_tests() {
    log "Running complete test suite..."
    
    local no_cleanup=false
    
    # Parse options
    while [[ $# -gt 0 ]]; do
        case $1 in
            --no-cleanup)
                no_cleanup=true
                shift
                ;;
            *)
                shift
                ;;
        esac
    done
    
    # Setup and start environment
    setup_environment
    start_containers
    install_plugin
    create_test_data
    
    # Run tests (placeholder for actual test execution)
    log "Executing test suite..."
    
    # Installation tests
    log "Running installation tests..."
    cd tests
    docker compose -f docker-compose.test.yml exec moodle-test php -f /var/www/html/mod/quiz/accessrule/invigilator/tests/installation_test.php
    
    # Integration tests
    log "Running integration tests..."
    docker compose -f docker-compose.test.yml exec moodle-test php -f /var/www/html/mod/quiz/accessrule/invigilator/tests/integration_test.php
    
    cd ..
    
    success "Test suite completed"
    
    # Cleanup unless specified otherwise
    if [ "$no_cleanup" = false ]; then
        cleanup_environment
    fi
}

# Clean up test environment
cleanup_environment() {
    log "Cleaning up test environment..."
    
    cd tests
    docker compose -f docker-compose.test.yml down -v
    cd ..
    
    # Remove test files
    rm -rf tests/results/*
    rm -rf tests/logs/*
    
    success "Test environment cleaned up"
}

# Reset test environment
reset_environment() {
    log "Resetting test environment..."
    
    cleanup_environment
    setup_environment
    
    success "Test environment reset"
}

# Show container logs
show_logs() {
    log "Showing container logs..."
    
    cd tests
    docker compose -f docker-compose.test.yml logs -f
    cd ..
}

# Access container shell
access_shell() {
    log "Accessing Moodle test container shell..."
    
    cd tests
    docker compose -f docker-compose.test.yml exec moodle-test bash
    cd ..
}

# Show container status
show_status() {
    log "Container status:"
    
    cd tests
    docker compose -f docker-compose.test.yml ps
    cd ..
}

# Main execution
main() {
    case "${1:-}" in
        setup)
            setup_environment
            ;;
        start)
            start_containers
            ;;
        stop)
            stop_containers
            ;;
        clean)
            cleanup_environment
            ;;
        test)
            shift
            run_tests "$@"
            ;;
        install)
            install_plugin
            ;;
        reset)
            reset_environment
            ;;
        logs)
            show_logs
            ;;
        shell)
            access_shell
            ;;
        status)
            show_status
            ;;
        -h|--help)
            show_help
            ;;
        *)
            echo "Unknown command: ${1:-}"
            echo "Use '$0 --help' for usage information"
            exit 1
            ;;
    esac
}

# Execute main function with all arguments
main "$@"