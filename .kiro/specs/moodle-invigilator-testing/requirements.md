# Requirements Document

## Introduction

This document outlines the requirements for setting up a comprehensive testing environment for the Moodle Invigilator plugin using Docker with Moodle 4.4.3. The Invigilator plugin is a quiz access control plugin that captures screenshots during quiz attempts to monitor student behavior and prevent cheating.

## Glossary

- **Docker_Environment**: A containerized setup running Moodle 4.4.3 with all necessary dependencies
- **Invigilator_Plugin**: The quizaccess_invigilator plugin that captures screenshots during quiz attempts
- **Test_Suite**: A comprehensive collection of automated and manual tests to validate plugin functionality
- **Moodle_Instance**: A running Moodle 4.4.3 installation accessible via web browser
- **Plugin_Installation**: The process of installing and configuring the Invigilator plugin in Moodle
- **Screenshot_Capture**: The core functionality that takes periodic screenshots during quiz attempts
- **Quiz_Access_Control**: Moodle's system for managing additional restrictions on quiz attempts

## Requirements

### Requirement 1

**User Story:** As a developer, I want to set up a Docker-based Moodle 4.4.3 environment, so that I can test the Invigilator plugin in a clean, reproducible environment.

#### Acceptance Criteria

1. THE Docker_Environment SHALL provide a running Moodle 4.4.3 instance accessible via web browser
2. THE Docker_Environment SHALL include all necessary dependencies for Moodle operation including PHP, MySQL, and web server
3. THE Docker_Environment SHALL persist data between container restarts
4. THE Docker_Environment SHALL be configurable through environment variables
5. WHEN the Docker_Environment is started, THE Moodle_Instance SHALL be accessible on a specified port

### Requirement 2

**User Story:** As a developer, I want to install the Invigilator plugin in the Moodle environment, so that I can test its functionality with Moodle 4.4.3.

#### Acceptance Criteria

1. THE Plugin_Installation SHALL copy the plugin files to the correct Moodle directory structure
2. THE Plugin_Installation SHALL complete the Moodle plugin installation process without errors
3. WHEN the Plugin_Installation is complete, THE Invigilator_Plugin SHALL appear in the Moodle plugins list
4. THE Plugin_Installation SHALL be compatible with Moodle 4.4.3
5. WHEN accessing quiz settings, THE Quiz_Access_Control SHALL include the Screenshot capture validation option

### Requirement 3

**User Story:** As a developer, I want to create comprehensive tests for the Invigilator plugin, so that I can verify all functionality works correctly with Moodle 4.4.3.

#### Acceptance Criteria

1. THE Test_Suite SHALL include tests for plugin installation and activation
2. THE Test_Suite SHALL include tests for quiz configuration with screenshot capture enabled
3. THE Test_Suite SHALL include tests for screenshot capture functionality during quiz attempts
4. THE Test_Suite SHALL include tests for admin reporting features
5. THE Test_Suite SHALL include tests for permission handling and user consent

### Requirement 4

**User Story:** As a developer, I want to test the screenshot capture functionality, so that I can ensure it works properly in the Docker environment.

#### Acceptance Criteria

1. WHEN a student starts a quiz with screenshot capture enabled, THE Invigilator_Plugin SHALL request screen sharing permission
2. WHEN permission is granted, THE Screenshot_Capture SHALL take screenshots at configured intervals
3. THE Screenshot_Capture SHALL store images in the Moodle data directory as PNG files
4. IF permission is denied, THEN THE Invigilator_Plugin SHALL prevent quiz access
5. THE Screenshot_Capture SHALL capture the entire screen surface including all tabs

### Requirement 5

**User Story:** As a developer, I want to test the admin reporting functionality, so that I can verify administrators can review captured screenshots.

#### Acceptance Criteria

1. THE Invigilator_Plugin SHALL provide an admin interface for viewing captured screenshots
2. THE admin interface SHALL display screenshots organized by student and quiz attempt
3. THE admin interface SHALL allow filtering and searching of screenshot records
4. WHEN accessing the report, THE admin interface SHALL load without errors
5. THE admin interface SHALL display screenshot metadata including timestamp and student information

### Requirement 6

**User Story:** As a developer, I want to validate plugin compatibility, so that I can ensure the plugin works correctly with Moodle 4.4.3 features and APIs.

#### Acceptance Criteria

1. THE Invigilator_Plugin SHALL be compatible with Moodle 4.4.3 database schema
2. THE Invigilator_Plugin SHALL work with Moodle 4.4.3 user interface components
3. THE Invigilator_Plugin SHALL integrate properly with Moodle 4.4.3 quiz engine
4. THE Invigilator_Plugin SHALL respect Moodle 4.4.3 security and privacy settings
5. WHEN running Moodle upgrade checks, THE Invigilator_Plugin SHALL pass all compatibility tests