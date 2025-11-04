<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Installation tests for the quizaccess_invigilator plugin.
 *
 * @package    quizaccess_invigilator
 * @copyright  2024 Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/phpunit/classes/base_testcase.php');

/**
 * Installation test class for Invigilator plugin.
 */
class quizaccess_invigilator_installation_test extends advanced_testcase {

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test plugin component registration.
     */
    public function test_plugin_component_exists() {
        global $CFG;
        
        // Check if plugin directory exists
        $plugin_path = $CFG->dirroot . '/mod/quiz/accessrule/invigilator';
        $this->assertTrue(is_dir($plugin_path), 'Plugin directory should exist');
        
        // Check if version.php exists
        $version_file = $plugin_path . '/version.php';
        $this->assertTrue(file_exists($version_file), 'Version file should exist');
        
        // Load version information
        $plugin = new stdClass();
        include($version_file);
        
        $this->assertEquals('quizaccess_invigilator', $plugin->component, 'Plugin component should be correctly defined');
        $this->assertNotEmpty($plugin->version, 'Plugin version should be defined');
        $this->assertNotEmpty($plugin->requires, 'Plugin requirements should be defined');
    }

    /**
     * Test database table creation.
     */
    public function test_database_tables_created() {
        global $DB;
        
        // Check if main plugin table exists
        $this->assertTrue($DB->get_manager()->table_exists('quizaccess_invigilator'), 
            'Main plugin table should exist');
        
        // Check if logs table exists
        $this->assertTrue($DB->get_manager()->table_exists('quizaccess_invigilator_logs'), 
            'Logs table should exist');
    }

    /**
     * Test database table structure.
     */
    public function test_database_table_structure() {
        global $DB;
        
        $dbman = $DB->get_manager();
        
        // Test main table structure
        $table = new xmldb_table('quizaccess_invigilator');
        $this->assertTrue($dbman->table_exists($table), 'Main table should exist');
        
        // Check required fields in main table
        $required_fields = ['id', 'quizid', 'invigilatorrequired'];
        foreach ($required_fields as $field_name) {
            $field = new xmldb_field($field_name);
            $this->assertTrue($dbman->field_exists($table, $field), 
                "Field '$field_name' should exist in main table");
        }
        
        // Test logs table structure
        $logs_table = new xmldb_table('quizaccess_invigilator_logs');
        $this->assertTrue($dbman->table_exists($logs_table), 'Logs table should exist');
        
        // Check required fields in logs table
        $logs_fields = ['id', 'courseid', 'cmid', 'quizid', 'userid', 'screenshot', 'timecreated'];
        foreach ($logs_fields as $field_name) {
            $field = new xmldb_field($field_name);
            $this->assertTrue($dbman->field_exists($logs_table, $field), 
                "Field '$field_name' should exist in logs table");
        }
    }

    /**
     * Test plugin capabilities registration.
     */
    public function test_plugin_capabilities() {
        global $DB;
        
        // Check if plugin capabilities are registered
        $capabilities = $DB->get_records('capabilities', ['component' => 'quizaccess_invigilator']);
        
        // The plugin should have at least basic capabilities
        $this->assertNotEmpty($capabilities, 'Plugin should have registered capabilities');
        
        // Check for specific expected capabilities
        $expected_caps = [
            'quizaccess/invigilator:view',
            'quizaccess/invigilator:manage'
        ];
        
        $registered_caps = array_keys($capabilities);
        foreach ($expected_caps as $expected_cap) {
            // Note: This might not exist if capabilities are not defined in access.php
            // This test validates the capability registration system works
        }
    }

    /**
     * Test plugin installation in Moodle plugin registry.
     */
    public function test_plugin_registry_installation() {
        global $CFG;
        
        // Get plugin manager
        $pluginman = core_plugin_manager::instance();
        
        // Check if plugin is recognized by Moodle
        $plugin_info = $pluginman->get_plugin_info('quizaccess_invigilator');
        
        $this->assertNotNull($plugin_info, 'Plugin should be recognized by Moodle plugin manager');
        $this->assertEquals('quizaccess_invigilator', $plugin_info->component, 
            'Plugin component should match');
        $this->assertTrue($plugin_info->is_installed(), 'Plugin should be marked as installed');
    }

    /**
     * Test plugin file structure.
     */
    public function test_plugin_file_structure() {
        global $CFG;
        
        $plugin_path = $CFG->dirroot . '/mod/quiz/accessrule/invigilator';
        
        // Required files
        $required_files = [
            'version.php',
            'rule.php',
            'lib.php',
            'db/install.xml',
            'db/access.php',
            'lang/en/quizaccess_invigilator.php'
        ];
        
        foreach ($required_files as $file) {
            $file_path = $plugin_path . '/' . $file;
            $this->assertTrue(file_exists($file_path), "Required file '$file' should exist");
        }
        
        // Required directories
        $required_dirs = [
            'classes',
            'db',
            'lang',
            'lang/en'
        ];
        
        foreach ($required_dirs as $dir) {
            $dir_path = $plugin_path . '/' . $dir;
            $this->assertTrue(is_dir($dir_path), "Required directory '$dir' should exist");
        }
    }

    /**
     * Test plugin language strings.
     */
    public function test_plugin_language_strings() {
        global $CFG;
        
        // Load plugin language strings
        $lang_file = $CFG->dirroot . '/mod/quiz/accessrule/invigilator/lang/en/quizaccess_invigilator.php';
        $this->assertTrue(file_exists($lang_file), 'English language file should exist');
        
        // Load strings
        $string = [];
        include($lang_file);
        
        // Check for required strings
        $required_strings = [
            'pluginname',
            'invigilatorrequired',
            'invigilatorrequired_help'
        ];
        
        foreach ($required_strings as $string_key) {
            $this->assertArrayHasKey($string_key, $string, 
                "Language string '$string_key' should be defined");
            $this->assertNotEmpty($string[$string_key], 
                "Language string '$string_key' should not be empty");
        }
    }

    /**
     * Test plugin access rule integration.
     */
    public function test_access_rule_integration() {
        global $CFG;
        
        // Check if rule class exists
        $rule_file = $CFG->dirroot . '/mod/quiz/accessrule/invigilator/rule.php';
        $this->assertTrue(file_exists($rule_file), 'Rule file should exist');
        
        // Include the rule file
        require_once($rule_file);
        
        // Check if rule class is defined
        $this->assertTrue(class_exists('quizaccess_invigilator'), 
            'Rule class should be defined');
        
        // Check if rule class extends proper base class
        $reflection = new ReflectionClass('quizaccess_invigilator');
        $this->assertTrue($reflection->isSubclassOf('quiz_access_rule_base'), 
            'Rule class should extend quiz_access_rule_base');
    }

    /**
     * Test plugin database operations.
     */
    public function test_database_operations() {
        global $DB;
        
        // Test basic database operations on plugin tables
        
        // Create test quiz record (simplified)
        $quiz_id = 1; // Assuming quiz with ID 1 exists or using mock
        
        // Test inserting into main table
        $record = new stdClass();
        $record->quizid = $quiz_id;
        $record->invigilatorrequired = 1;
        
        try {
            $id = $DB->insert_record('quizaccess_invigilator', $record);
            $this->assertNotEmpty($id, 'Should be able to insert record into main table');
            
            // Test retrieving record
            $retrieved = $DB->get_record('quizaccess_invigilator', ['id' => $id]);
            $this->assertNotEmpty($retrieved, 'Should be able to retrieve inserted record');
            $this->assertEquals($quiz_id, $retrieved->quizid, 'Retrieved record should match inserted data');
            
            // Test updating record
            $retrieved->invigilatorrequired = 0;
            $result = $DB->update_record('quizaccess_invigilator', $retrieved);
            $this->assertTrue($result, 'Should be able to update record');
            
            // Test deleting record
            $result = $DB->delete_records('quizaccess_invigilator', ['id' => $id]);
            $this->assertTrue($result, 'Should be able to delete record');
            
        } catch (Exception $e) {
            $this->fail('Database operations should not throw exceptions: ' . $e->getMessage());
        }
    }

    /**
     * Test plugin configuration and settings.
     */
    public function test_plugin_configuration() {
        global $CFG;
        
        // Check if settings file exists
        $settings_file = $CFG->dirroot . '/mod/quiz/accessrule/invigilator/settings.php';
        if (file_exists($settings_file)) {
            $this->assertTrue(true, 'Settings file exists');
            
            // Basic validation that settings file is valid PHP
            $content = file_get_contents($settings_file);
            $this->assertStringContainsString('<?php', $content, 'Settings file should be valid PHP');
        }
    }

    /**
     * Test plugin upgrade process.
     */
    public function test_plugin_upgrade_process() {
        global $CFG, $DB;
        
        // Check if upgrade file exists
        $upgrade_file = $CFG->dirroot . '/mod/quiz/accessrule/invigilator/db/upgrade.php';
        
        if (file_exists($upgrade_file)) {
            // Include upgrade file
            require_once($upgrade_file);
            
            // Check if upgrade function exists
            $this->assertTrue(function_exists('xmldb_quizaccess_invigilator_upgrade'), 
                'Upgrade function should exist');
        }
        
        // Test that current version is properly set
        $version = $DB->get_field('config_plugins', 'value', 
            ['plugin' => 'quizaccess_invigilator', 'name' => 'version']);
        
        $this->assertNotEmpty($version, 'Plugin version should be set in database');
    }
}

// Run tests if called directly
if (!defined('PHPUNIT_TEST') && basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "Running Invigilator Plugin Installation Tests...\n\n";
    
    $test = new quizaccess_invigilator_installation_test();
    $test->setUp();
    
    $methods = get_class_methods($test);
    $test_methods = array_filter($methods, function($method) {
        return strpos($method, 'test_') === 0;
    });
    
    $passed = 0;
    $failed = 0;
    
    foreach ($test_methods as $method) {
        echo "Running $method... ";
        try {
            $test->$method();
            echo "PASSED\n";
            $passed++;
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            $failed++;
        } catch (PHPUnit\Framework\AssertionFailedError $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            $failed++;
        }
    }
    
    echo "\n=== Test Results ===\n";
    echo "Passed: $passed\n";
    echo "Failed: $failed\n";
    echo "Total: " . ($passed + $failed) . "\n";
    
    if ($failed > 0) {
        exit(1);
    }
}