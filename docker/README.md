# Moodle Invigilator Plugin - Docker Test Environment

This directory contains Docker configuration files for setting up a complete Moodle 4.4.3 testing environment with the Invigilator plugin.

## Quick Start

1. **Start the environment:**
   ```bash
   ./docker/start.sh
   ```

2. **Access Moodle:**
   - URL: http://localhost:8080
   - Admin Username: admin
   - Admin Password: Admin123!

3. **Stop the environment:**
   ```bash
   ./docker/stop.sh
   ```

## Configuration Files

- `docker-compose.yml` - Main Docker Compose configuration
- `.env` - Environment variables for easy customization
- `moodle-config/` - Moodle initialization scripts
- `mysql-config/` - MySQL initialization scripts

## Services

### Moodle Container
- **Image:** moodle:4.4.3-apache
- **Port:** 8080 (configurable via .env)
- **Volumes:** 
  - Plugin files mounted from current directory
  - Persistent data storage for moodledata

### MySQL Container
- **Image:** mysql:8.0
- **Port:** 3306 (configurable via .env)
- **Database:** moodle
- **Persistent storage:** mysql_data volume

## Environment Variables

Edit the `.env` file to customize:

- `MOODLE_PORT` - Port for Moodle web interface (default: 8080)
- `MOODLE_ADMIN_USER` - Admin username (default: admin)
- `MOODLE_ADMIN_PASSWORD` - Admin password (default: Admin123!)
- `MYSQL_PASSWORD` - Database password
- And more...

## Plugin Installation

The Invigilator plugin is automatically mounted into the container at:
`/var/www/html/mod/quiz/accessrule/invigilator`

The installation script (`01-install-plugin.sh`) runs during container startup to:
1. Wait for Moodle initialization
2. Run plugin installation via Moodle CLI
3. Verify plugin activation

## Troubleshooting

### Container won't start
```bash
# Check container logs
docker compose logs moodle
docker compose logs mysql

# Restart containers
docker compose restart
```

### Plugin not visible
```bash
# Access Moodle container
docker compose exec moodle bash

# Check plugin directory
ls -la /var/www/html/mod/quiz/accessrule/invigilator

# Run Moodle upgrade manually
cd /var/www/html
php admin/cli/upgrade.php --non-interactive
```

### Database connection issues
```bash
# Check MySQL status
docker compose exec mysql mysqladmin ping -h localhost -u root -proot_password

# Reset database
docker compose down -v
docker compose up -d
```

## Data Persistence

- **Moodle data:** Stored in `moodle_data` Docker volume
- **MySQL data:** Stored in `mysql_data` Docker volume
- **Plugin files:** Mounted from host directory (read-only)

To completely reset the environment:
```bash
docker compose down -v
docker compose up -d
```