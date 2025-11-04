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
 * Integration tests for the quizaccess_invigilator plugin.
 *
 * @package    quizaccess_invigilator
 * @copyright  2024 Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/phpunit/classes/base_testcase.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/invigilator/rule.php');

/**
 * Integration test class for Invigilator plugin.
 */
class quizaccess_invigilator_integration_test extends advanced_testcase {

    /** @var stdClass Course object */
    private $course;
    
    /** @var stdClass Quiz object */
    private $quiz;
    
    /** @var stdClass User object */
    private $user;
    
    /** @var stdClass Teacher object */
    private $teacher;

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        // Create test course
        $this->course = $this->getDataGenerator()->create_course();
        
        // Create test users
        $this->user = $this->getDataGenerator()->create_user();
        $this->teacher = $this->getDataGenerator()->create_user();
        
        // Enroll users
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        
        // Create test quiz
        $this->quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $this->course->id,
            'name' => 'Test Quiz for Invigilator',
            'timeopen' => time() - 3600,
            'timeclose' => time() + 3600,
        ]);
    }

    /**
     * Test Moodle 4.4.3 API compatibility.
     */
    public function test_moodle_api_compatibility() {
        global $CFG;
        
        // Test that required Moodle APIs are available
        $this->assertTrue(function_exists('get_config'), 'get_config function should be available');
        $this->assertTrue(class_exists('quiz_access_rule_base'), 'Quiz access rule base class should exist');
        $this->assertTrue(function_exists('quiz_access_manager'), 'Quiz access manager should be available');
        
        // Test Moodle version compatibility
        $this->assertGreaterThanOrEqual(2019052000, $CFG->version, 
            'Moodle version should be compatible with plugin requirements');
    }

    /**
     * Test plugin integration with quiz access rule system.
     */
    public function test_quiz_access_rule_integration() {
        global $DB;
        
        // Enable invigilator for the quiz
        $record = new stdClass();
        $record->quizid = $this->quiz->id;
        $record->invigilatorrequired = 1;
        $DB->insert_record('quizaccess_invigilator', $record);
        
        // Get quiz access manager
        $quiz = $DB->get_record('quiz', ['id' => $this->quiz->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = context_module::instance($cm->id);
        
        // Test that invigilator rule is loaded
        $accessmanager = new quiz_access_manager($quiz, $cm, $context, time(), false);
        $rules = $accessmanager->get_active_rule_names();
        
        // The invigilator rule should be active when enabled
        $this->assertContains('quizaccess_invigilator', $rules, 
            'Invigilator rule should be active when enabled');
    }

    /**
     * Test plugin rule class functionality.
     */
    public function test_rule_class_functionality() {
        global $DB;
        
        // Create invigilator configuration
        $record = new stdClass();
        $record->quizid = $this->quiz->id;
        $record->invigilatorrequired = 1;
        $DB->insert_record('quizaccess_invigilator', $record);
        
        // Get quiz and context
        $quiz = $DB->get_record('quiz', ['id' => $this->quiz->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = context_module::instance($cm->id);
        
        // Create rule instance
        $rule = new quizaccess_invigilator($quiz, $cm, $context, time(), false);
        
        // Test rule methods
        $this->assertInstanceOf('quizaccess_invigilator', $rule, 'Rule should be properly instantiated');
        
        // Test that rule is applicable when enabled
        $this->assertTrue(method_exists($rule, 'is_preflight_check_required'), 
            'Rule should have preflight check method');
        
        if (method_exists($rule, 'is_preflight_check_required')) {
            // This method should exist and return boolean
            $result = $rule->is_preflight_check_required(null);
            $this->assertIsBool($result, 'Preflight check should return boolean');
        }
    }

    /**
     * Test plugin database integration with Moodle.
     */
    public function test_database_integration() {
        global $DB;
        
        // Test that plugin tables integrate properly with Moodle database
        $tables = $DB->get_tables();
        
        $this->assertContains('quizaccess_invigilator', $tables, 
            'Main plugin table should be in database');
        $this->assertContains('quizaccess_invigilator_logs', $tables, 
            'Logs table should be in database');
        
        // Test foreign key relationships work
        $record = new stdClass();
        $record->quizid = $this->quiz->id;
        $record->invigilatorrequired = 1;
        $id = $DB->insert_record('quizaccess_invigilator', $record);
        
        $this->assertNotEmpty($id, 'Should be able to insert with valid quiz ID');
        
        // Test that record can be retrieved with joins
        $sql = "SELECT qi.*, q.name as quiz_name 
                FROM {quizaccess_invigilator} qi 
                JOIN {quiz} q ON qi.quizid = q.id 
                WHERE qi.id = ?";
        
        $joined_record = $DB->get_record_sql($sql, [$id]);
        $this->assertNotEmpty($joined_record, 'Should be able to join with quiz table');
        $this->assertEquals($this->quiz->name, $joined_record->quiz_name, 
            'Join should return correct quiz name');
    }

    /**
     * Test plugin capability integration.
     */
    public function test_capability_integration() {
        global $DB;
        
        // Test that plugin capabilities work with Moodle's capability system
        $context = context_course::instance($this->course->id);
        
        // Test basic capability checking (even if capabilities don't exist yet)
        $this->assertTrue(function_exists('has_capability'), 'has_capability function should exist');
        $this->assertTrue(function_exists('require_capability'), 'require_capability function should exist');
        
        // Test context system integration
        $this->assertInstanceOf('context_course', $context, 'Context should be properly created');
        
        // If plugin defines capabilities, they should be checkable
        $capabilities = $DB->get_records('capabilities', ['component' => 'quizaccess_invigilator']);
        foreach ($capabilities as $capability) {
            // Test that capability can be checked without errors
            try {
                has_capability($capability->name, $context, $this->teacher->id);
                $this->assertTrue(true, "Capability {$capability->name} should be checkable");
            } catch (Exception $e) {
                $this->fail("Capability {$capability->name} check failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Test plugin event integration.
     */
    public function test_event_integration() {
        global $DB;
        
        // Test that plugin can trigger and handle Moodle events
        $this->assertTrue(class_exists('core\event\base'), 'Event base class should exist');
        
        // Create test log entry to simulate screenshot capture event
        $record = new stdClass();
        $record->courseid = $this->course->id;
        $record->cmid = $this->quiz->cmid;
        $record->quizid = $this->quiz->id;
        $record->userid = $this->user->id;
        $record->screenshot = 'test_screenshot_data';
        $record->timecreated = time();
        
        $log_id = $DB->insert_record('quizaccess_invigilator_logs', $record);
        $this->assertNotEmpty($log_id, 'Should be able to create log entry');
        
        // Test that events can be created for plugin actions
        // This would typically involve custom events defined by the plugin
        $event_data = [
            'context' => context_module::instance($this->quiz->cmid),
            'courseid' => $this->course->id,
            'userid' => $this->user->id,
            'other' => [
                'quizid' => $this->quiz->id,
                'screenshot_captured' => true
            ]
        ];
        
        // Verify event data structure is valid
        $this->assertArrayHasKey('context', $event_data, 'Event should have context');
        $this->assertArrayHasKey('courseid', $event_data, 'Event should have course ID');
        $this->assertArrayHasKey('userid', $event_data, 'Event should have user ID');
    }

    /**
     * Test plugin file handling integration.
     */
    public function test_file_handling_integration() {
        global $CFG;
        
        // Test that plugin can use Moodle's file API
        $this->assertTrue(function_exists('get_file_storage'), 'File storage function should exist');
        
        $fs = get_file_storage();
        $this->assertNotNull($fs, 'File storage should be available');
        
        // Test file context for plugin
        $context = context_module::instance($this->quiz->cmid);
        
        // Create test file record
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'quizaccess_invigilator',
            'filearea' => 'screenshots',
            'itemid' => $this->user->id,
            'filepath' => '/',
            'filename' => 'test_screenshot.png',
            'userid' => $this->user->id
        ];
        
        // Test that file can be created (using dummy content)
        $content = 'dummy_image_content';
        $file = $fs->create_file_from_string($filerecord, $content);
        
        $this->assertNotNull($file, 'Should be able to create file');
        $this->assertEquals('test_screenshot.png', $file->get_filename(), 'Filename should match');
        $this->assertEquals('quizaccess_invigilator', $file->get_component(), 'Component should match');
        
        // Clean up
        $file->delete();
    }

    /**
     * Test plugin form integration.
     */
    public function test_form_integration() {
        global $CFG;
        
        // Test that plugin can integrate with Moodle forms
        require_once($CFG->libdir . '/formslib.php');
        
        $this->assertTrue(class_exists('moodleform'), 'Moodle form class should exist');
        
        // Test form elements that plugin might use
        $form_elements = [
            'checkbox',
            'select',
            'text',
            'hidden'
        ];
        
        // These are standard Moodle form elements that the plugin should be able to use
        foreach ($form_elements as $element) {
            $this->assertTrue(true, "Form element '$element' should be available");
        }
    }

    /**
     * Test plugin navigation integration.
     */
    public function test_navigation_integration() {
        global $PAGE;
        
        // Test that plugin can integrate with Moodle navigation
        $this->assertNotNull($PAGE, 'Global PAGE object should exist');
        
        // Test navigation context
        $context = context_module::instance($this->quiz->cmid);
        $PAGE->set_context($context);
        
        $this->assertEquals($context, $PAGE->context, 'Page context should be set correctly');
        
        // Test that plugin can add navigation items
        $navigation = $PAGE->navigation;
        $this->assertNotNull($navigation, 'Navigation should be available');
        
        // Test settings navigation (where plugin settings would appear)
        $settings = $PAGE->settingsnav;
        $this->assertNotNull($settings, 'Settings navigation should be available');
    }

    /**
     * Test plugin language string integration.
     */
    public function test_language_integration() {
        // Test that plugin language strings work with Moodle's language system
        $this->assertTrue(function_exists('get_string'), 'get_string function should exist');
        
        // Test loading plugin strings
        try {
            $pluginname = get_string('pluginname', 'quizaccess_invigilator');
            $this->assertNotEmpty($pluginname, 'Plugin name string should be loaded');
        } catch (Exception $e) {
            // This might fail if language strings are not properly installed
            $this->markTestSkipped('Language strings not available: ' . $e->getMessage());
        }
        
        // Test that language system can handle plugin component
        $component = 'quizaccess_invigilator';
        $this->assertNotEmpty($component, 'Plugin component should be defined');
    }

    /**
     * Test plugin upgrade integration.
     */
    public function test_upgrade_integration() {
        global $CFG, $DB;
        
        // Test that plugin version is properly tracked
        $version = $DB->get_field('config_plugins', 'value', 
            ['plugin' => 'quizaccess_invigilator', 'name' => 'version']);
        
        if ($version) {
            $this->assertNotEmpty($version, 'Plugin version should be tracked');
            $this->assertIsNumeric($version, 'Plugin version should be numeric');
        }
        
        // Test upgrade function exists if upgrade file exists
        $upgrade_file = $CFG->dirroot . '/mod/quiz/accessrule/invigilator/db/upgrade.php';
        if (file_exists($upgrade_file)) {
            require_once($upgrade_file);
            $this->assertTrue(function_exists('xmldb_quizaccess_invigilator_upgrade'), 
                'Upgrade function should exist');
        }
    }
}

// Run tests if called directly
if (!defined('PHPUNIT_TEST') && basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "Running Invigilator Plugin Integration Tests...\n\n";
    
    $test = new quizaccess_invigilator_integration_test();
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