-- MySQL initialization script for Moodle Invigilator testing
-- This script sets up the database with optimal settings for Moodle

-- Set MySQL configuration for Moodle compatibility
SET GLOBAL innodb_file_format = 'Barracuda';
SET GLOBAL innodb_file_per_table = 1;
SET GLOBAL innodb_large_prefix = 1;

-- Create additional indexes for better performance during testing
-- These will be created after Moodle installation completes

-- Grant additional privileges for testing
GRANT ALL PRIVILEGES ON *.* TO 'moodle'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;

-- Log initialization completion
SELECT 'MySQL database initialized for Moodle Invigilator testing' as status;