# Plugin Installation and Validation Scripts

This directory contains comprehensive scripts for installing and validating the Moodle Invigilator plugin with Moodle 4.4.3.

## Overview

The installation and validation system consists of several components:

1. **Plugin Installation Scripts** - Automate the installation process
2. **Structure Validation** - Verify plugin file structure and syntax
3. **Compatibility Validation** - Check Moodle 4.4.3 compatibility
4. **Database Validation** - Validate database schema and installation
5. **Activation Verification** - Verify plugin activation and integration

## Scripts

### Installation Scripts

#### `plugin-installer.sh`
Main installation wrapper script that orchestrates the complete installation process.

```bash
# Install plugin with validation
./scripts/plugin-installer.sh install --verbose

# Upgrade existing installation
./scripts/plugin-installer.sh upgrade --non-interactive

# Check plugin status
./scripts/plugin-installer.sh check
```

#### `install-plugin.sh`
Core installation script that copies plugin files to Moodle directory.

```bash
# Install plugin files
MOODLE_ROOT=/var/www/html ./scripts/install-plugin.sh install

# Verify installation
./scripts/install-plugin.sh verify
```

#### `moodle-cli-install.php`
Moodle CLI script for database installation and upgrades.

```bash
# Install plugin via Moodle CLI
php ./scripts/moodle-cli-install.php --install --verbose

# Upgrade plugin
php ./scripts/moodle-cli-install.php --upgrade --non-interactive
```

### Validation Scripts

#### `comprehensive-validator.sh`
Main validation wrapper that runs all compatibility checks.

```bash
# Run all validations
./scripts/comprehensive-validator.sh all --verbose

# Run specific validation
./scripts/comprehensive-validator.sh structure --verbose

# Generate report
./scripts/comprehensive-validator.sh all --report-file validation-report.txt
```

#### `validate-plugin-structure.sh`
Validates plugin file structure and syntax.

```bash
# Validate plugin structure
./scripts/validate-plugin-structure.sh validate

# With custom plugin directory
PLUGIN_DIR=/path/to/plugin ./scripts/validate-plugin-structure.sh validate
```

#### `compatibility-validator.php`
Checks Moodle 4.4.3 API and feature compatibility.

```bash
# Run all compatibility checks
php ./scripts/compatibility-validator.php --check-all --verbose

# Check specific areas
php ./scripts/compatibility-validator.php --check-api --check-db
```

#### `database-validator.php`
Validates database schema and installation.

```bash
# Validate database schema
php ./scripts/database-validator.php --validate-schema --verbose

# Check existing tables
php ./scripts/database-validator.php --check-tables

# Test installation (dry run)
php ./scripts/database-validator.php --test-install --dry-run
```

#### `activation-validator.php`
Verifies plugin activation and Moodle integration.

```bash
# Check plugin activation
php ./scripts/activation-validator.php --check-installation --verbose

# Verify integration points
php ./scripts/activation-validator.php --check-integration --test-quiz-access
```

## Environment Variables

All scripts support the following environment variables:

- `MOODLE_ROOT` - Path to Moodle installation (default: `/var/www/html`)
- `PLUGIN_SOURCE_DIR` - Path to plugin source files (default: current directory)

## Usage Examples

### Complete Installation Process

```bash
# 1. Validate plugin structure first
./scripts/comprehensive-validator.sh structure --verbose

# 2. Install plugin files and run Moodle installation
./scripts/plugin-installer.sh install --verbose

# 3. Verify installation and activation
./scripts/comprehensive-validator.sh activation --verbose
```

### Docker Environment Setup

```bash
# Set environment for Docker container
export MOODLE_ROOT=/var/www/html
export PLUGIN_SOURCE_DIR=/plugin-source

# Run comprehensive validation
./scripts/comprehensive-validator.sh all --verbose --report-file /reports/validation.txt

# Install if validation passes
./scripts/plugin-installer.sh install --non-interactive
```

### Continuous Integration

```bash
# CI pipeline validation
./scripts/comprehensive-validator.sh all \
  --continue-on-error \
  --report-file ci-validation-report.txt \
  --verbose

# Exit with proper code for CI
if [ $? -eq 0 ]; then
  echo "✓ Plugin validation passed"
else
  echo "✗ Plugin validation failed"
  exit 1
fi
```

## Validation Checks

### Structure Validation
- Core files existence (version.php, rule.php, etc.)
- Directory structure compliance
- PHP syntax validation
- Language files validation
- Database schema files validation

### Compatibility Validation
- Moodle version requirements
- API compatibility checks
- Database schema compatibility
- Capabilities structure validation
- Web services validation

### Database Validation
- XML schema structure validation
- Table and field definitions
- Key and index validation
- Installation simulation (dry run)

### Activation Validation
- Plugin registration in database
- Capabilities registration
- Web services registration
- Moodle integration points
- Quiz access rule functionality

## Error Handling

All scripts include comprehensive error handling:

- **Exit Codes**: Scripts return appropriate exit codes (0 = success, 1 = failure)
- **Verbose Logging**: Use `--verbose` flag for detailed output
- **Continue on Error**: Use `--continue-on-error` to run all checks even if some fail
- **Report Generation**: Use `--report-file` to save detailed reports

## Requirements

### System Requirements
- Bash shell (for shell scripts)
- PHP CLI (for PHP scripts)
- Access to Moodle installation directory
- Appropriate file system permissions

### Moodle Requirements
- Moodle 4.4.3 or compatible version
- MySQL/MariaDB database
- Web server (Apache/Nginx)
- PHP 8.1 or higher

## Troubleshooting

### Common Issues

1. **Permission Denied**
   ```bash
   chmod +x scripts/*.sh
   ```

2. **Moodle Not Found**
   ```bash
   export MOODLE_ROOT=/path/to/moodle
   ```

3. **PHP CLI Not Available**
   ```bash
   # Install PHP CLI
   sudo apt-get install php-cli  # Ubuntu/Debian
   sudo yum install php-cli      # CentOS/RHEL
   ```

4. **Database Connection Issues**
   - Ensure Moodle config.php is properly configured
   - Check database credentials and connectivity
   - Verify database user permissions

### Debug Mode

Enable verbose output for debugging:

```bash
# Enable verbose mode for all scripts
export VERBOSE=true

# Or use --verbose flag
./scripts/plugin-installer.sh install --verbose
```

## Integration with Docker

These scripts are designed to work seamlessly with Docker environments:

```dockerfile
# Example Dockerfile usage
COPY scripts/ /plugin-scripts/
RUN chmod +x /plugin-scripts/*.sh

# Install plugin during container build
RUN /plugin-scripts/plugin-installer.sh install --non-interactive

# Or validate during CI
RUN /plugin-scripts/comprehensive-validator.sh all --continue-on-error
```

## Support

For issues or questions:

1. Check the verbose output for detailed error messages
2. Review the generated reports for specific failure details
3. Ensure all requirements are met
4. Verify environment variables are set correctly

## Contributing

When modifying these scripts:

1. Maintain backward compatibility
2. Add appropriate error handling
3. Update documentation
4. Test with different Moodle versions
5. Follow existing coding standards