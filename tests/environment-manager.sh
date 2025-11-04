#!/bin/bash

# Environment Setup and Teardown Automation for Moodle Invigilator Testing
# Manages test environment lifecycle with proper cleanup and state management

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
TEST_ENV_NAME="moodle-invigilator-test"
BACKUP_DIR="$SCRIPT_DIR/backups"
STATE_FILE="$SCRIPT_DIR/.env-state"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${BLUE}[ENV-MGR $(date +'%H:%M:%S')]${NC} $1"
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

# Save environment state
save_state() {
    local state="$1"
    echo "STATE=$state" > "$STATE_FILE"
    echo "TIMESTAMP=$(date +%s)" >> "$STATE_FILE"
    echo "PID=$$" >> "$STATE_FILE"
}

# Load environment state
load_state() {
    if [ -f "$STATE_FILE" ]; then
        source "$STATE_FILE"
        echo "$STATE"
    else
        echo "clean"
    fi
}

# Check if environment is running
is_environment_running() {
    cd "$SCRIPT_DIR"
    if [ -f "docker-compose.test.yml" ]; then
        docker compose -f docker-compose.test.yml ps --services --filter "status=running" | grep -q "moodle-test"
    else
        false
    fi
}

# Validate environment prerequisites
validate_prerequisites() {
    log "Validating prerequisites..."
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        error "Docker is not installed"
        return 1
    fi
    
    # Check Docker Compose
    if ! docker compose version &> /dev/null; then
        error "Docker Compose is not available"
        return 1
    fi
    
    # Check available disk space (minimum 2GB)
    local available_space=$(df "$SCRIPT_DIR" | awk 'NR==2 {print $4}')
    if [ "$available_space" -lt 2097152 ]; then
        warning "Low disk space detected. At least 2GB recommended for testing"
    fi
    
    # Check available memory
    if command -v free &> /dev/null; then
        local available_memory=$(free -m | awk 'NR==2{printf "%.0f", $7}')
        if [ "$available_memory" -lt 1024 ]; then
            warning "Low memory detected. At least 1GB recommended for testing"
        fi
    fi
    
    success "Prerequisites validation passed"
}

# Setup test environment
setup_environment() {
    log "Setting up test environment..."
    
    validate_prerequisites
    
    # Create necessary directories
    mkdir -p "$SCRIPT_DIR"/{data,results,logs,fixtures,sql,scripts,backups}
    
    # Generate test configuration if not exists
    if [ ! -f "$SCRIPT_DIR/.env.test" ]; then
        generate_test_env_file
    fi
    
    # Create Docker Compose file if not exists
    if [ ! -f "$SCRIPT_DIR/docker-compose.test.yml" ]; then
        create_test_compose_file
    fi
    
    # Create initialization scripts
    create_initialization_scripts
    
    # Create test fixtures
    create_test_fixtures
    
    save_state "setup"
    success "Test environment setup completed"
}

# Generate test environment file
generate_test_env_file() {
    log "Generating test environment configuration..."
    
    cat > "$SCRIPT_DIR/.env.test" << EOF
# Moodle Test Environment Configuration
MOODLE_DATABASE_TYPE=mysqli
MOODLE_DATABASE_HOST=mysql-test
MOODLE_DATABASE_NAME=moodle_test
MOODLE_DATABASE_USER=moodle_test
MOODLE_DATABASE_PASSWORD=test_password_123
MOODLE_DATABASE_PORT=3306

# Moodle Admin Configuration
MOODLE_ADMIN_USER=testadmin
MOODLE_ADMIN_PASSWORD=TestAdmin123!
MOODLE_ADMIN_EMAIL=testadmin@example.com

# Site Configuration
MOODLE_SITE_NAME=Moodle Invigilator Test Environment
MOODLE_SITE_SHORTNAME=invigilator-test
MOODLE_WWWROOT=http://localhost:8081

# Debug Configuration
MOODLE_DEBUG=15
MOODLE_DEBUG_DISPLAY=1
MOODLE_DEBUG_DEVELOPER=1

# Performance Configuration
MOODLE_CACHE_STORES=file
MOODLE_SESSION_HANDLER=file

# Test-specific Configuration
TEST_MODE=1
INVIGILATOR_TEST_MODE=1
EOF
}

# Create Docker Compose test file
create_test_compose_file() {
    log "Creating test Docker Compose configuration..."
    
    cat > "$SCRIPT_DIR/docker-compose.test.yml" << EOF
services:
  moodle-test:
    image: moodle:4.4.3-apache
    container_name: moodle-invigilator-test-app
    ports:
      - "8081:80"
    env_file:
      - .env.test
    volumes:
      - moodle_test_data:/var/www/moodledata
      - ../:/var/www/html/mod/quiz/accessrule/invigilator:ro
      - ./fixtures:/var/www/html/test-fixtures:ro
      - ./scripts:/test-scripts:ro
      - ./logs:/test-logs:rw
    depends_on:
      mysql-test:
        condition: service_healthy
    networks:
      - test_network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/admin/cli/check_database_schema.php", "||", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 10
      start_period: 120s
    environment:
      - PHP_MEMORY_LIMIT=512M
      - PHP_MAX_EXECUTION_TIME=300

  mysql-test:
    image: mysql:8.0
    container_name: moodle-invigilator-test-db
    environment:
      - MYSQL_ROOT_PASSWORD=root_test_password_123
      - MYSQL_DATABASE=moodle_test
      - MYSQL_USER=moodle_test
      - MYSQL_PASSWORD=test_password_123
      - MYSQL_CHARSET=utf8mb4
      - MYSQL_COLLATION=utf8mb4_unicode_ci
    volumes:
      - mysql_test_data:/var/lib/mysql
      - ./sql:/docker-entrypoint-initdb.d:ro
      - ./logs:/test-logs:rw
    ports:
      - "3307:3306"
    networks:
      - test_network
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-proot_test_password_123"]
      interval: 10s
      timeout: 5s
      retries: 10
      start_period: 60s
    command: >
      --default-authentication-plugin=mysql_native_password
      --innodb-buffer-pool-size=256M
      --max-connections=100
      --wait-timeout=28800

volumes:
  moodle_test_data:
    driver: local
    name: moodle_invigilator_test_data
  mysql_test_data:
    driver: local
    name: moodle_invigilator_test_mysql

networks:
  test_network:
    driver: bridge
    name: moodle_invigilator_test_network
EOF
}

# Create initialization scripts
create_initialization_scripts() {
    log "Creating initialization scripts..."
    
    # Database initialization
    cat > "$SCRIPT_DIR/sql/01-test-database-setup.sql" << 'EOF'
-- Test Database Initialization
-- Create additional test database if needed

CREATE DATABASE IF NOT EXISTS moodle_test_backup;
GRANT ALL PRIVILEGES ON moodle_test_backup.* TO 'moodle_test'@'%';

-- Set optimal settings for testing
SET GLOBAL innodb_flush_log_at_trx_commit = 2;
SET GLOBAL sync_binlog = 0;
SET GLOBAL innodb_buffer_pool_size = 268435456; -- 256MB

FLUSH PRIVILEGES;
EOF

    # Moodle initialization script
    cat > "$SCRIPT_DIR/scripts/01-moodle-init.sh" << 'EOF'
#!/bin/bash
set -e

echo "Initializing Moodle for testing..."

# Wait for database to be ready
until mysqladmin ping -h mysql-test -u moodle_test -ptest_password_123 --silent; do
    echo "Waiting for database..."
    sleep 2
done

# Install Moodle if not already installed
if [ ! -f /var/www/moodledata/installed ]; then
    echo "Installing Moodle..."
    cd /var/www/html
    
    # Run Moodle installation
    php admin/cli/install_database.php \
        --agree-license \
        --adminuser=testadmin \
        --adminpass=TestAdmin123! \
        --adminemail=testadmin@example.com \
        --fullname="Moodle Invigilator Test Environment" \
        --shortname="invigilator-test"
    
    touch /var/www/moodledata/installed
    echo "Moodle installation completed"
else
    echo "Moodle already installed"
fi

# Upgrade database if needed
cd /var/www/html
php admin/cli/upgrade.php --non-interactive

echo "Moodle initialization completed"
EOF

    # Plugin installation script
    cat > "$SCRIPT_DIR/scripts/02-plugin-install.sh" << 'EOF'
#!/bin/bash
set -e

echo "Installing Invigilator plugin..."

cd /var/www/html

# Check if plugin is already installed
if php admin/cli/cfg.php --name=version | grep -q "quizaccess_invigilator"; then
    echo "Plugin already installed, upgrading..."
else
    echo "Installing plugin for the first time..."
fi

# Run upgrade to install/update plugin
php admin/cli/upgrade.php --non-interactive

# Verify plugin installation
if php admin/cli/uninstall_plugins.php --show-all | grep -q "quizaccess_invigilator"; then
    echo "Plugin installation verified"
else
    echo "Plugin installation failed"
    exit 1
fi

echo "Plugin installation completed"
EOF

    chmod +x "$SCRIPT_DIR/scripts/"*.sh
}

# Create test fixtures
create_test_fixtures() {
    log "Creating test fixtures..."
    
    # Test course data
    cat > "$SCRIPT_DIR/fixtures/test-course.json" << 'EOF'
{
    "course": {
        "fullname": "Invigilator Test Course",
        "shortname": "INVTEST001",
        "category": 1,
        "summary": "Course for testing Invigilator plugin functionality",
        "format": "topics",
        "numsections": 3,
        "visible": 1
    },
    "users": [
        {
            "username": "teststudent1",
            "password": "TestStudent123!",
            "firstname": "Test",
            "lastname": "Student One",
            "email": "teststudent1@example.com",
            "role": "student"
        },
        {
            "username": "teststudent2", 
            "password": "TestStudent123!",
            "firstname": "Test",
            "lastname": "Student Two",
            "email": "teststudent2@example.com",
            "role": "student"
        },
        {
            "username": "testteacher",
            "password": "TestTeacher123!",
            "firstname": "Test",
            "lastname": "Teacher",
            "email": "testteacher@example.com",
            "role": "teacher"
        }
    ],
    "quiz": {
        "name": "Invigilator Test Quiz",
        "intro": "Quiz for testing screenshot capture functionality",
        "timeopen": 0,
        "timeclose": 0,
        "timelimit": 1800,
        "attempts": 3,
        "grademethod": 1,
        "questions": [
            {
                "type": "multichoice",
                "name": "Test Question 1",
                "questiontext": "What is the primary purpose of the Invigilator plugin?",
                "answers": [
                    {"text": "To capture screenshots during quiz attempts", "fraction": 1},
                    {"text": "To grade quizzes automatically", "fraction": 0},
                    {"text": "To create quiz questions", "fraction": 0},
                    {"text": "To manage user accounts", "fraction": 0}
                ]
            }
        ]
    }
}
EOF

    # Test configuration
    cat > "$SCRIPT_DIR/fixtures/invigilator-config.json" << 'EOF'
{
    "screenshot_interval": 30,
    "capture_enabled": true,
    "require_permission": true,
    "storage_path": "/var/www/moodledata/invigilator",
    "image_format": "png",
    "image_quality": 80,
    "max_file_size": 5242880,
    "retention_days": 30
}
EOF
}

# Start test environment
start_environment() {
    log "Starting test environment..."
    
    if is_environment_running; then
        warning "Test environment is already running"
        return 0
    fi
    
    cd "$SCRIPT_DIR"
    
    # Start containers
    docker compose -f docker-compose.test.yml up -d
    
    # Wait for services to be healthy
    log "Waiting for services to start..."
    
    local max_wait=300  # 5 minutes
    local wait_time=0
    
    while [ $wait_time -lt $max_wait ]; do
        if docker compose -f docker-compose.test.yml ps --services --filter "status=running" | grep -q "moodle-test"; then
            if docker compose -f docker-compose.test.yml exec mysql-test mysqladmin ping -h localhost -u root -proot_test_password_123 --silent 2>/dev/null; then
                break
            fi
        fi
        
        sleep 10
        wait_time=$((wait_time + 10))
        log "Waiting... ($wait_time/${max_wait}s)"
    done
    
    if [ $wait_time -ge $max_wait ]; then
        error "Timeout waiting for services to start"
        return 1
    fi
    
    # Initialize Moodle and plugin
    log "Initializing Moodle..."
    docker compose -f docker-compose.test.yml exec moodle-test /test-scripts/01-moodle-init.sh
    
    log "Installing plugin..."
    docker compose -f docker-compose.test.yml exec moodle-test /test-scripts/02-plugin-install.sh
    
    save_state "running"
    success "Test environment started successfully"
    
    log "Environment is ready:"
    log "  Moodle: http://localhost:8081"
    log "  Admin: testadmin / TestAdmin123!"
    log "  MySQL: localhost:3307"
}

# Stop test environment
stop_environment() {
    log "Stopping test environment..."
    
    cd "$SCRIPT_DIR"
    
    if [ -f "docker-compose.test.yml" ]; then
        docker compose -f docker-compose.test.yml down
        save_state "stopped"
        success "Test environment stopped"
    else
        warning "No test environment configuration found"
    fi
}

# Create backup of test environment
backup_environment() {
    log "Creating environment backup..."
    
    local backup_name="backup-$(date +%Y%m%d-%H%M%S)"
    local backup_path="$BACKUP_DIR/$backup_name"
    
    mkdir -p "$backup_path"
    
    cd "$SCRIPT_DIR"
    
    # Backup database
    if is_environment_running; then
        log "Backing up database..."
        docker compose -f docker-compose.test.yml exec mysql-test mysqldump \
            -u root -proot_test_password_123 \
            --single-transaction --routines --triggers \
            moodle_test > "$backup_path/database.sql"
    fi
    
    # Backup Moodle data
    if docker volume ls | grep -q "moodle_invigilator_test_data"; then
        log "Backing up Moodle data..."
        docker run --rm \
            -v moodle_invigilator_test_data:/source:ro \
            -v "$backup_path":/backup \
            alpine tar czf /backup/moodledata.tar.gz -C /source .
    fi
    
    # Backup configuration
    cp -r "$SCRIPT_DIR"/{.env.test,fixtures,scripts} "$backup_path/" 2>/dev/null || true
    
    success "Backup created: $backup_path"
}

# Restore environment from backup
restore_environment() {
    local backup_name="$1"
    
    if [ -z "$backup_name" ]; then
        error "Backup name required"
        return 1
    fi
    
    local backup_path="$BACKUP_DIR/$backup_name"
    
    if [ ! -d "$backup_path" ]; then
        error "Backup not found: $backup_path"
        return 1
    fi
    
    log "Restoring environment from backup: $backup_name"
    
    # Stop current environment
    stop_environment
    
    # Clean volumes
    docker volume rm moodle_invigilator_test_data moodle_invigilator_test_mysql 2>/dev/null || true
    
    # Restore configuration
    cp "$backup_path"/{.env.test,fixtures,scripts} "$SCRIPT_DIR/" 2>/dev/null || true
    
    # Start environment
    start_environment
    
    # Restore database
    if [ -f "$backup_path/database.sql" ]; then
        log "Restoring database..."
        cd "$SCRIPT_DIR"
        docker compose -f docker-compose.test.yml exec -T mysql-test mysql \
            -u root -proot_test_password_123 \
            moodle_test < "$backup_path/database.sql"
    fi
    
    # Restore Moodle data
    if [ -f "$backup_path/moodledata.tar.gz" ]; then
        log "Restoring Moodle data..."
        docker run --rm \
            -v moodle_invigilator_test_data:/target \
            -v "$backup_path":/backup:ro \
            alpine tar xzf /backup/moodledata.tar.gz -C /target
    fi
    
    success "Environment restored from backup"
}

# Clean up test environment
cleanup_environment() {
    log "Cleaning up test environment..."
    
    cd "$SCRIPT_DIR"
    
    # Stop and remove containers
    if [ -f "docker-compose.test.yml" ]; then
        docker compose -f docker-compose.test.yml down -v --remove-orphans
    fi
    
    # Remove Docker volumes
    docker volume rm moodle_invigilator_test_data moodle_invigilator_test_mysql 2>/dev/null || true
    
    # Remove Docker network
    docker network rm moodle_invigilator_test_network 2>/dev/null || true
    
    # Clean up test files
    rm -rf "$SCRIPT_DIR"/{data,results,logs}/*
    
    # Remove state file
    rm -f "$STATE_FILE"
    
    save_state "clean"
    success "Test environment cleaned up"
}

# Show environment status
show_status() {
    local current_state=$(load_state)
    
    log "Environment Status: $current_state"
    
    if [ -f "$SCRIPT_DIR/docker-compose.test.yml" ]; then
        cd "$SCRIPT_DIR"
        echo ""
        log "Container Status:"
        docker compose -f docker-compose.test.yml ps
        
        echo ""
        log "Volume Status:"
        docker volume ls | grep "moodle_invigilator_test" || echo "No test volumes found"
        
        if is_environment_running; then
            echo ""
            log "Service Health:"
            echo "  Moodle: $(curl -s -o /dev/null -w "%{http_code}" http://localhost:8081 || echo "Not accessible")"
            echo "  MySQL: $(docker compose -f docker-compose.test.yml exec mysql-test mysqladmin ping -h localhost -u root -proot_test_password_123 --silent 2>/dev/null && echo "OK" || echo "Not accessible")"
        fi
    else
        warning "No test environment configuration found"
    fi
}

# Main function
main() {
    case "${1:-}" in
        setup)
            setup_environment
            ;;
        start)
            start_environment
            ;;
        stop)
            stop_environment
            ;;
        cleanup)
            cleanup_environment
            ;;
        backup)
            backup_environment
            ;;
        restore)
            restore_environment "$2"
            ;;
        status)
            show_status
            ;;
        *)
            echo "Usage: $0 {setup|start|stop|cleanup|backup|restore|status}"
            echo ""
            echo "Commands:"
            echo "  setup    - Set up test environment"
            echo "  start    - Start test environment"
            echo "  stop     - Stop test environment"
            echo "  cleanup  - Clean up test environment"
            echo "  backup   - Create environment backup"
            echo "  restore  - Restore from backup"
            echo "  status   - Show environment status"
            exit 1
            ;;
    esac
}

main "$@"