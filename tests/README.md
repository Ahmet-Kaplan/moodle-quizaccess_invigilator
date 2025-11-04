# Moodle Invigilator Plugin Testing Framework

This directory contains a comprehensive testing framework for the Moodle Invigilator plugin, designed to validate all aspects of plugin functionality in a Docker-based Moodle 4.4.3 environment.

## Overview

The testing framework provides:

- **Docker Test Orchestration**: Automated container management for isolated testing
- **Installation & Activation Tests**: Validation of plugin installation and database setup
- **Unit Tests**: Core functionality testing for plugin components
- **Integration Tests**: Moodle 4.4.3 compatibility and API integration validation
- **Automated Test Execution**: Comprehensive test runner with reporting

## Test Structure

### Test Categories

1. **Installation Tests** (`installation_test.php`)
   - Plugin component registration
   - Database table creation and structure
   - File system validation
   - Language string verification
   - Plugin registry integration

2. **Database Tests** (`database_test.php`)
   - Table structure validation
   - CRUD operations testing
   - Constraint and index verification
   - Transaction handling
   - Performance testing

3. **Integration Tests** (`integration_test.php`)
   - Moodle 4.4.3 API compatibility
   - Quiz access rule system integration
   - Capability and permission system
   - Event system integration
   - File handling integration

4. **Screenshot Capture Tests** (`screenshot_capture_test.php`)
   - Screenshot data validation
   - File storage functionality
   - Timestamp addition
   - Parameter validation
   - Error handling

5. **Quiz Access Control Tests** (`quiz_access_control_test.php`)
   - Rule instantiation and properties
   - Preflight check requirements
   - Settings form integration
   - Access control validation
   - Quiz system integration

6. **Admin Reporting Tests** (`admin_reporting_test.php`)
   - Log retrieval and filtering
   - Data aggregation and statistics
   - Report access permissions
   - Data export functionality
   - Performance testing

## Docker Test Environment

### Components

- **Test Orchestration** (`docker-test-runner.sh`)
  - Container lifecycle management
  - Environment setup and teardown
  - Test data initialization
  - Automated test execution

- **Environment Manager** (`environment-manager.sh`)
  - Docker Compose configuration
  - Service health monitoring
  - Backup and restore functionality
  - State management

- **Test Data Initializer** (`test-data-initializer.sh`)
  - Course and quiz creation
  - User provisioning and enrollment
  - Plugin configuration
  - Sample data generation

### Usage

#### Setup Test Environment

```bash
# Set up complete test environment
./tests/docker-test-runner.sh setup

# Start test containers
./tests/docker-test-runner.sh start

# Initialize test data
./tests/test-data-initializer.sh init
```

#### Run Tests

```bash
# Run complete test suite
./tests/docker-test-runner.sh test

# Run tests without cleanup
./tests/docker-test-runner.sh test --no-cleanup

# Run specific test category
./tests/run-all-tests.sh --suite installation
```

#### Environment Management

```bash
# Check environment status
./tests/environment-manager.sh status

# Create backup
./tests/environment-manager.sh backup

# Clean up environment
./tests/environment-manager.sh cleanup
```

## Test Execution

### Automated Test Runner

The `run-all-tests.sh` script provides comprehensive test execution:

```bash
# Run all tests with reports
./tests/run-all-tests.sh

# Run specific test suite
./tests/run-all-tests.sh --suite screenshot_capture

# Generate JSON report only
./tests/run-all-tests.sh --json-only

# Verbose output
./tests/run-all-tests.sh --verbose
```

### Individual Test Execution

Each test file can be run independently:

```bash
# Run installation tests
php tests/installation_test.php

# Run database tests
php tests/database_test.php

# Run integration tests
php tests/integration_test.php
```

## Test Configuration

### Environment Variables

The test environment uses the following configuration:

```bash
# Moodle Configuration
MOODLE_DATABASE_TYPE=mysqli
MOODLE_DATABASE_HOST=mysql-test
MOODLE_DATABASE_NAME=moodle_test
MOODLE_ADMIN_USER=testadmin
MOODLE_ADMIN_PASSWORD=TestAdmin123!

# Test Configuration
TEST_MODE=1
INVIGILATOR_TEST_MODE=1
```

### Test Data

The framework creates:

- **Test Course**: "Invigilator Test Course" (INVTEST001)
- **Test Quiz**: "Invigilator Test Quiz" with screenshot capture enabled
- **Test Users**: Students, teachers, and managers with appropriate permissions
- **Sample Screenshots**: Mock screenshot data for testing

## Reporting

### HTML Report

Comprehensive HTML report with:
- Test execution summary
- Individual test results
- Detailed output logs
- Pass/fail statistics

Generated at: `tests/results/test_report.html`

### JSON Report

Machine-readable JSON report for CI/CD integration:

```json
{
  "timestamp": "2024-01-01T12:00:00Z",
  "test_run": {
    "total_suites": 6,
    "passed": 5,
    "failed": 1,
    "suites": [
      {
        "name": "installation_test",
        "status": "passed",
        "file": "installation_test.php"
      }
    ]
  }
}
```

Generated at: `tests/results/test_results.json`

## Requirements

### System Requirements

- Docker and Docker Compose
- PHP 8.1+ (for local test execution)
- Bash shell
- curl (for health checks)

### Moodle Requirements

- Moodle 4.4.3 environment
- MySQL 8.0 database
- Required PHP extensions (GD, mysqli, etc.)

## Troubleshooting

### Common Issues

1. **Container Startup Failures**
   ```bash
   # Check container logs
   ./tests/docker-test-runner.sh logs
   
   # Verify Docker is running
   docker --version
   docker compose version
   ```

2. **Database Connection Issues**
   ```bash
   # Check MySQL container health
   ./tests/environment-manager.sh status
   
   # Reset environment
   ./tests/environment-manager.sh cleanup
   ./tests/environment-manager.sh setup
   ```

3. **Test Failures**
   ```bash
   # Run with verbose output
   ./tests/run-all-tests.sh --verbose
   
   # Check individual test logs
   cat tests/logs/installation_test.log
   ```

4. **Permission Issues**
   ```bash
   # Ensure scripts are executable
   chmod +x tests/*.sh
   
   # Check file permissions
   ls -la tests/
   ```

### Debug Mode

Enable debug mode for detailed output:

```bash
# Set debug environment variable
export MOODLE_DEBUG=1

# Run tests with verbose logging
./tests/run-all-tests.sh --verbose
```

## Development

### Adding New Tests

1. Create new test file following naming convention: `{category}_test.php`
2. Extend `advanced_testcase` class
3. Implement test methods with `test_` prefix
4. Add to `TEST_SUITES` array in `run-all-tests.sh`

### Test Structure Template

```php
<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/phpunit/classes/base_testcase.php');

class quizaccess_invigilator_new_test extends advanced_testcase {
    
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        // Setup test data
    }
    
    public function test_functionality() {
        // Test implementation
        $this->assertTrue(true, 'Test should pass');
    }
}
```

### Best Practices

- Use `resetAfterTest(true)` to ensure test isolation
- Create minimal test data required for each test
- Use descriptive test method names
- Include both positive and negative test cases
- Verify error handling and edge cases
- Keep tests focused and independent

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Moodle Invigilator Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup Test Environment
        run: ./tests/docker-test-runner.sh setup
        
      - name: Run Tests
        run: ./tests/run-all-tests.sh --json-only
        
      - name: Upload Results
        uses: actions/upload-artifact@v2
        with:
          name: test-results
          path: tests/results/
```

### Jenkins Pipeline Example

```groovy
pipeline {
    agent any
    
    stages {
        stage('Setup') {
            steps {
                sh './tests/docker-test-runner.sh setup'
            }
        }
        
        stage('Test') {
            steps {
                sh './tests/run-all-tests.sh'
            }
        }
        
        stage('Report') {
            steps {
                publishHTML([
                    allowMissing: false,
                    alwaysLinkToLastBuild: true,
                    keepAll: true,
                    reportDir: 'tests/results',
                    reportFiles: 'test_report.html',
                    reportName: 'Test Report'
                ])
            }
        }
    }
    
    post {
        always {
            sh './tests/docker-test-runner.sh clean'
        }
    }
}
```

## Support

For issues with the testing framework:

1. Check the troubleshooting section above
2. Review test logs in `tests/logs/`
3. Verify Docker environment is properly configured
4. Ensure Moodle plugin is correctly installed

## License

This testing framework is part of the Moodle Invigilator plugin and is licensed under the GNU GPL v3 or later.