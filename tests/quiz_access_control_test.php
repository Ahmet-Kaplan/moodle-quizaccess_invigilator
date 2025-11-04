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
 * Quiz access control unit tests for the quizaccess_invigilator plugin.
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
 * Quiz access control test class for Invigilator plugin.
 */
class quizaccess_invigilator_quiz_access_control_test extends advanced_testcase {

    /** @var stdClass Course object */
    private $course;
    
    /** @var stdClass Quiz object */
    private $quiz;
    
    /** @var stdClass User object */
    private $user;
    
    /** @var context_module Module context */
    private $context;
    
    /** @var quizaccess_invigilator Rule instance */
    private $rule;

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        // Create test course
        $this->course = $this->getDataGenerator()->create_course();
        
        // Create test user
        $this->user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
        
        // Create test quiz
        $this->quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $this->course->id,
            'name' => 'Test Quiz for Access Control',
        ]);
        
        $this->context = context_module::instance($this->quiz->cmid);
        
        // Set current user
        $this->setUser($this->user);
    }

    /**
     * Create rule instance with invigilator enabled.
     */
    private function create_rule_instance($invigilator_required = true) {
        global $DB;
        
        // Enable invigilator for the quiz
        if ($invigilator_required) {
            $record = new stdClass();
            $record->quizid = $this->quiz->id;
            $record->invigilatorrequired = 1;
            $DB->insert_record('quizaccess_invigilator', $record);
        }
        
        // Get updated quiz object
        $quiz = $DB->get_record('quiz', ['id' => $this->quiz->id]);
        $quiz->invigilatorrequired = $invigilator_required ? 1 : 0;
        
        // Create quiz object
        $quizobj = new quiz($quiz, get_coursemodule_from_instance('quiz', $quiz->id), $this->course);
        
        // Create rule instance
        $this->rule = new quizaccess_invigilator($quizobj, time());
        
        return $this->rule;
    }

    /**
     * Test rule instantiation and basic properties.
     */
    public function test_rule_instantiation() {
        $rule = $this->create_rule_instance();
        
        $this->assertInstanceOf('quizaccess_invigilator', $rule, 'Rule should be properly instantiated');
        $this->assertInstanceOf('quiz_access_rule_base', $rule, 'Rule should extend base class');
    }

    /**
     * Test preflight check requirement.
     */
    public function test_preflight_check_requirement() {
        $rule = $this->create_rule_instance();
        
        // Mock the script detection
        $reflection = new ReflectionClass($rule);
        $method = $reflection->getMethod('get_topmost_script');
        $method->setAccessible(true);
        
        // Test that preflight check is required for view.php
        $is_required = $rule->is_preflight_check_required(null);
        $this->assertIsBool($is_required, 'Preflight check requirement should return boolean');
    }

    /**
     * Test rule creation based on quiz settings.
     */
    public function test_rule_creation() {
        global $DB;
        
        // Test with invigilator disabled
        $quiz = $DB->get_record('quiz', ['id' => $this->quiz->id]);
        $quiz->invigilatorrequired = 0;
        $quizobj = new quiz($quiz, get_coursemodule_from_instance('quiz', $quiz->id), $this->course);
        
        $rule = quizaccess_invigilator::make($quizobj, time(), false);
        $this->assertNull($rule, 'Rule should not be created when invigilator is disabled');
        
        // Test with invigilator enabled
        $quiz->invigilatorrequired = 1;
        $quizobj = new quiz($quiz, get_coursemodule_from_instance('quiz', $quiz->id), $this->course);
        
        $rule = quizaccess_invigilator::make($quizobj, time(), false);
        $this->assertInstanceOf('quizaccess_invigilator', $rule, 
            'Rule should be created when invigilator is enabled');
    }

    /**
     * Test settings form fields addition.
     */
    public function test_settings_form_fields() {
        // Create mock form objects
        $quiz_form = $this->createMock('mod_quiz_mod_form');
        $mform = $this->createMock('MoodleQuickForm');
        
        // Expect addElement to be called for invigilator settings
        $mform->expects($this->atLeastOnce())
              ->method('addElement')
              ->with('select', 'invigilatorrequired');
        
        $mform->expects($this->atLeastOnce())
              ->method('addHelpButton')
              ->with('invigilatorrequired', 'invigilatorrequired', 'quizaccess_invigilator');
        
        // Test adding settings form fields
        quizaccess_invigilator::add_settings_form_fields($quiz_form, $mform);
        
        $this->assertTrue(true, 'Settings form fields should be added without errors');
    }

    /**
     * Test settings save functionality.
     */
    public function test_settings_save() {
        global $DB;
        
        // Test saving with invigilator enabled
        $quiz_data = new stdClass();
        $quiz_data->id = $this->quiz->id;
        $quiz_data->invigilatorrequired = 1;
        
        quizaccess_invigilator::save_settings($quiz_data);
        
        $record = $DB->get_record('quizaccess_invigilator', ['quizid' => $this->quiz->id]);
        $this->assertNotEmpty($record, 'Record should be created when invigilator is enabled');
        $this->assertEquals(1, $record->invigilatorrequired, 'Invigilator should be marked as required');
        
        // Test saving with invigilator disabled
        $quiz_data->invigilatorrequired = 0;
        quizaccess_invigilator::save_settings($quiz_data);
        
        $record = $DB->get_record('quizaccess_invigilator', ['quizid' => $this->quiz->id]);
        $this->assertFalse($record, 'Record should be deleted when invigilator is disabled');
    }

    /**
     * Test settings deletion.
     */
    public function test_settings_deletion() {
        global $DB;
        
        // Create invigilator record
        $record = new stdClass();
        $record->quizid = $this->quiz->id;
        $record->invigilatorrequired = 1;
        $DB->insert_record('quizaccess_invigilator', $record);
        
        // Verify record exists
        $this->assertTrue($DB->record_exists('quizaccess_invigilator', ['quizid' => $this->quiz->id]), 
            'Record should exist before deletion');
        
        // Delete settings
        $quiz_data = new stdClass();
        $quiz_data->id = $this->quiz->id;
        quizaccess_invigilator::delete_settings($quiz_data);
        
        // Verify record is deleted
        $this->assertFalse($DB->record_exists('quizaccess_invigilator', ['quizid' => $this->quiz->id]), 
            'Record should be deleted');
    }

    /**
     * Test SQL settings loading.
     */
    public function test_settings_sql() {
        $sql_parts = quizaccess_invigilator::get_settings_sql($this->quiz->id);
        
        $this->assertIsArray($sql_parts, 'SQL parts should be an array');
        $this->assertCount(3, $sql_parts, 'SQL parts should have 3 elements');
        
        list($fields, $joins, $params) = $sql_parts;
        
        $this->assertEquals('invigilatorrequired', $fields, 'Fields should include invigilatorrequired');
        $this->assertStringContainsString('LEFT JOIN', $joins, 'Joins should include LEFT JOIN');
        $this->assertStringContainsString('quizaccess_invigilator', $joins, 'Joins should reference plugin table');
        $this->assertIsArray($params, 'Params should be an array');
    }

    /**
     * Test preflight form validation.
     */
    public function test_preflight_form_validation() {
        $rule = $this->create_rule_instance();
        
        // Test validation with checkbox unchecked
        $data = ['invigilator' => 0];
        $files = [];
        $errors = [];
        
        $result_errors = $rule->validate_preflight_check($data, $files, $errors, null);
        
        $this->assertArrayHasKey('invigilator', $result_errors, 
            'Validation should fail when checkbox is unchecked');
        $this->assertNotEmpty($result_errors['invigilator'], 
            'Error message should be provided');
        
        // Test validation with checkbox checked
        $data = ['invigilator' => 1];
        $result_errors = $rule->validate_preflight_check($data, $files, $errors, null);
        
        $this->assertArrayNotHasKey('invigilator', $result_errors, 
            'Validation should pass when checkbox is checked');
    }

    /**
     * Test rule description generation.
     */
    public function test_rule_description() {
        global $PAGE;
        
        $rule = $this->create_rule_instance();
        
        // Set up page context
        $PAGE->set_context($this->context);
        
        $description = $rule->description();
        
        $this->assertIsArray($description, 'Description should be an array');
        $this->assertNotEmpty($description, 'Description should not be empty');
        
        // Check that description contains expected elements
        $description_text = implode(' ', $description);
        $this->assertNotEmpty($description_text, 'Description text should not be empty');
    }

    /**
     * Test modal content generation.
     */
    public function test_modal_content_generation() {
        $rule = $this->create_rule_instance();
        
        $quiz_form = $this->createMock('mod_quiz_preflight_check_form');
        $modal_content = $rule->make_modal_content($quiz_form);
        
        $this->assertIsString($modal_content, 'Modal content should be a string');
        $this->assertNotEmpty($modal_content, 'Modal content should not be empty');
        $this->assertStringContainsString('<div', $modal_content, 'Modal content should contain HTML');
        $this->assertStringContainsString('<table', $modal_content, 'Modal content should contain table');
    }

    /**
     * Test course and quiz data extraction.
     */
    public function test_course_quiz_data_extraction() {
        $rule = $this->create_rule_instance();
        
        $course_data = $rule->get_courseid_cmid_from_preflight_form();
        
        $this->assertIsArray($course_data, 'Course data should be an array');
        $this->assertArrayHasKey('courseid', $course_data, 'Should contain course ID');
        $this->assertArrayHasKey('quizid', $course_data, 'Should contain quiz ID');
        $this->assertArrayHasKey('cmid', $course_data, 'Should contain CM ID');
        
        $this->assertEquals($this->course->id, $course_data['courseid'], 'Course ID should match');
        $this->assertEquals($this->quiz->id, $course_data['quizid'], 'Quiz ID should match');
        $this->assertEquals($this->quiz->cmid, $course_data['cmid'], 'CM ID should match');
    }

    /**
     * Test attempt page setup.
     */
    public function test_attempt_page_setup() {
        global $PAGE;
        
        $rule = $this->create_rule_instance();
        
        // Set up page
        $PAGE->set_context($this->context);
        $PAGE->set_title('Test Quiz Attempt');
        
        // Test page setup
        $rule->setup_attempt_page($PAGE);
        
        // Verify page properties are set
        $this->assertStringContainsString($this->course->shortname, $PAGE->title, 
            'Page title should include course shortname');
        $this->assertFalse($PAGE->popup_notification_allowed(), 
            'Popup notifications should be disabled');
    }

    /**
     * Test topmost script detection.
     */
    public function test_topmost_script_detection() {
        $rule = $this->create_rule_instance();
        
        $script = $rule->get_topmost_script();
        
        $this->assertIsString($script, 'Script path should be a string');
        $this->assertNotEmpty($script, 'Script path should not be empty');
        $this->assertStringContainsString('.php', $script, 'Script should be a PHP file');
    }

    /**
     * Test preflight form field addition.
     */
    public function test_preflight_form_field_addition() {
        global $PAGE;
        
        $rule = $this->create_rule_instance();
        
        // Set up page context
        $PAGE->set_context($this->context);
        
        // Create mock form objects
        $quiz_form = $this->createMock('mod_quiz_preflight_check_form');
        $mform = $this->createMock('MoodleQuickForm');
        
        // Set up form attributes
        $mform->_attributes = [];
        
        // Expect form elements to be added
        $mform->expects($this->atLeastOnce())
              ->method('addElement');
        
        // Test adding preflight form fields
        $rule->add_preflight_check_form_fields($quiz_form, $mform, null);
        
        // Verify form target is set
        $this->assertEquals('_blank', $mform->_attributes['target'], 
            'Form target should be set to _blank');
    }

    /**
     * Test access rule integration with quiz system.
     */
    public function test_quiz_system_integration() {
        global $DB;
        
        // Enable invigilator for quiz
        $record = new stdClass();
        $record->quizid = $this->quiz->id;
        $record->invigilatorrequired = 1;
        $DB->insert_record('quizaccess_invigilator', $record);
        
        // Get quiz with invigilator settings
        $quiz = $DB->get_record('quiz', ['id' => $this->quiz->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        
        // Create quiz access manager
        $accessmanager = new quiz_access_manager($quiz, $cm, $this->context, time(), false);
        
        // Test that invigilator rule is recognized
        $rules = $accessmanager->get_active_rule_names();
        $this->assertContains('quizaccess_invigilator', $rules, 
            'Invigilator rule should be active');
    }
}

// Run tests if called directly
if (!defined('PHPUNIT_TEST') && basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "Running Invigilator Plugin Quiz Access Control Tests...\n\n";
    
    $test = new quizaccess_invigilator_quiz_access_control_test();
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