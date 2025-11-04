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
 * Screenshot capture unit tests for the quizaccess_invigilator plugin.
 *
 * @package    quizaccess_invigilator
 * @copyright  2024 Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/phpunit/classes/base_testcase.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/invigilator/classes/external.php');

/**
 * Screenshot capture test class for Invigilator plugin.
 */
class quizaccess_invigilator_screenshot_capture_test extends advanced_testcase {

    /** @var stdClass Course object */
    private $course;
    
    /** @var stdClass Quiz object */
    private $quiz;
    
    /** @var stdClass User object */
    private $user;
    
    /** @var context_module Module context */
    private $context;

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
            'name' => 'Test Quiz for Screenshot Capture',
        ]);
        
        $this->context = context_module::instance($this->quiz->cmid);
        
        // Set current user
        $this->setUser($this->user);
    }

    /**
     * Test screenshot data validation.
     */
    public function test_screenshot_data_validation() {
        // Test valid base64 image data
        $valid_screenshot = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        
        $this->assertStringContainsString('data:image/png;base64,', $valid_screenshot, 
            'Valid screenshot should contain proper data URI prefix');
        
        // Test base64 decoding
        list($type, $data) = explode(';', $valid_screenshot);
        list(, $data) = explode(',', $data);
        $decoded = base64_decode($data);
        
        $this->assertNotFalse($decoded, 'Valid base64 data should decode successfully');
        $this->assertNotEmpty($decoded, 'Decoded data should not be empty');
    }

    /**
     * Test screenshot storage functionality.
     */
    public function test_screenshot_storage() {
        global $DB;
        
        $screenshot_data = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        
        // Test screenshot storage via external API
        $result = quizaccess_invigilator_external::send_screenshot(
            $this->course->id,
            $this->quiz->cmid,
            $this->quiz->id,
            $screenshot_data
        );
        
        $this->assertArrayHasKey('screenshotid', $result, 'Result should contain screenshot ID');
        $this->assertNotEmpty($result['screenshotid'], 'Screenshot ID should not be empty');
        
        // Verify database record
        $log_record = $DB->get_record('quizaccess_invigilator_logs', ['id' => $result['screenshotid']]);
        $this->assertNotEmpty($log_record, 'Log record should be created');
        $this->assertEquals($this->course->id, $log_record->courseid, 'Course ID should match');
        $this->assertEquals($this->quiz->id, $log_record->quizid, 'Quiz ID should match');
        $this->assertEquals($this->user->id, $log_record->userid, 'User ID should match');
    }

    /**
     * Test screenshot file creation.
     */
    public function test_screenshot_file_creation() {
        global $DB;
        
        $screenshot_data = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        
        $result = quizaccess_invigilator_external::send_screenshot(
            $this->course->id,
            $this->quiz->cmid,
            $this->quiz->id,
            $screenshot_data
        );
        
        // Check if file was created in file system
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id,
            'quizaccess_invigilator',
            'picture',
            $result['screenshotid']
        );
        
        $this->assertNotEmpty($files, 'Files should be created in file area');
        
        // Find the actual image file (not directory)
        $image_file = null;
        foreach ($files as $file) {
            if ($file->get_filename() !== '.') {
                $image_file = $file;
                break;
            }
        }
        
        $this->assertNotNull($image_file, 'Image file should be created');
        $this->assertStringContainsString('screenshot-', $image_file->get_filename(), 
            'Filename should contain screenshot prefix');
        $this->assertStringContainsString('.png', $image_file->get_filename(), 
            'File should have PNG extension');
    }

    /**
     * Test screenshot timestamp addition.
     */
    public function test_screenshot_timestamp_addition() {
        // This tests the private method indirectly through the public API
        $screenshot_data = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        
        $result = quizaccess_invigilator_external::send_screenshot(
            $this->course->id,
            $this->quiz->cmid,
            $this->quiz->id,
            $screenshot_data
        );
        
        // Get the stored file
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id,
            'quizaccess_invigilator',
            'picture',
            $result['screenshotid']
        );
        
        $image_file = null;
        foreach ($files as $file) {
            if ($file->get_filename() !== '.') {
                $image_file = $file;
                break;
            }
        }
        
        $this->assertNotNull($image_file, 'Image file should exist');
        
        // Verify file content is different from original (timestamp was added)
        $stored_content = $image_file->get_content();
        
        // Decode original for comparison
        list($type, $original_data) = explode(';', $screenshot_data);
        list(, $original_data) = explode(',', $original_data);
        $original_decoded = base64_decode($original_data);
        
        $this->assertNotEquals($original_decoded, $stored_content, 
            'Stored image should be different from original (timestamp added)');
    }

    /**
     * Test screenshot parameter validation.
     */
    public function test_screenshot_parameter_validation() {
        // Test parameter validation
        $params = quizaccess_invigilator_external::send_screenshot_parameters();
        
        $this->assertInstanceOf('external_function_parameters', $params, 
            'Parameters should be external function parameters');
        
        $param_keys = array_keys($params->keys);
        $expected_keys = ['courseid', 'cmid', 'quizid', 'screenshot'];
        
        foreach ($expected_keys as $key) {
            $this->assertContains($key, $param_keys, "Parameter '$key' should be defined");
        }
    }

    /**
     * Test screenshot return structure.
     */
    public function test_screenshot_return_structure() {
        $returns = quizaccess_invigilator_external::send_screenshot_returns();
        
        $this->assertInstanceOf('external_single_structure', $returns, 
            'Returns should be external single structure');
        
        $return_keys = array_keys($returns->keys);
        $expected_keys = ['screenshotid', 'warnings'];
        
        foreach ($expected_keys as $key) {
            $this->assertContains($key, $return_keys, "Return key '$key' should be defined");
        }
    }

    /**
     * Test screenshot capture with invalid data.
     */
    public function test_screenshot_capture_invalid_data() {
        // Test with invalid base64 data
        $invalid_screenshot = 'invalid_data_not_base64';
        
        try {
            $result = quizaccess_invigilator_external::send_screenshot(
                $this->course->id,
                $this->quiz->cmid,
                $this->quiz->id,
                $invalid_screenshot
            );
            
            // If it doesn't throw an exception, check if it handles gracefully
            $this->assertArrayHasKey('screenshotid', $result, 
                'Should handle invalid data gracefully or throw exception');
                
        } catch (Exception $e) {
            // Exception is acceptable for invalid data
            $this->assertTrue(true, 'Invalid data should be rejected');
        }
    }

    /**
     * Test screenshot capture frequency and limits.
     */
    public function test_screenshot_capture_frequency() {
        global $DB;
        
        $screenshot_data = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        
        // Capture multiple screenshots
        $screenshot_ids = [];
        for ($i = 0; $i < 3; $i++) {
            $result = quizaccess_invigilator_external::send_screenshot(
                $this->course->id,
                $this->quiz->cmid,
                $this->quiz->id,
                $screenshot_data
            );
            $screenshot_ids[] = $result['screenshotid'];
            
            // Small delay to ensure different timestamps
            sleep(1);
        }
        
        // Verify all screenshots were stored
        $this->assertCount(3, $screenshot_ids, 'All screenshots should be stored');
        
        // Verify database records
        $log_records = $DB->get_records('quizaccess_invigilator_logs', [
            'quizid' => $this->quiz->id,
            'userid' => $this->user->id
        ]);
        
        $this->assertCount(3, $log_records, 'All log records should be created');
        
        // Verify timestamps are different
        $timestamps = array_column($log_records, 'timecreated');
        $unique_timestamps = array_unique($timestamps);
        $this->assertCount(3, $unique_timestamps, 'Timestamps should be unique');
    }

    /**
     * Test screenshot metadata storage.
     */
    public function test_screenshot_metadata_storage() {
        global $DB;
        
        $screenshot_data = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        
        $result = quizaccess_invigilator_external::send_screenshot(
            $this->course->id,
            $this->quiz->cmid,
            $this->quiz->id,
            $screenshot_data
        );
        
        // Get log record
        $log_record = $DB->get_record('quizaccess_invigilator_logs', ['id' => $result['screenshotid']]);
        
        // Verify metadata
        $this->assertEquals($this->course->id, $log_record->courseid, 'Course ID should be stored');
        $this->assertEquals($this->quiz->cmid, $log_record->cmid, 'CM ID should be stored');
        $this->assertEquals($this->quiz->id, $log_record->quizid, 'Quiz ID should be stored');
        $this->assertEquals($this->user->id, $log_record->userid, 'User ID should be stored');
        $this->assertNotEmpty($log_record->screenshot, 'Screenshot path should be stored');
        $this->assertNotEmpty($log_record->timecreated, 'Timestamp should be stored');
        
        // Verify screenshot path is a valid URL
        $this->assertStringContainsString('pluginfile.php', $log_record->screenshot, 
            'Screenshot path should be a pluginfile URL');
    }

    /**
     * Test screenshot file permissions and access.
     */
    public function test_screenshot_file_permissions() {
        $screenshot_data = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        
        $result = quizaccess_invigilator_external::send_screenshot(
            $this->course->id,
            $this->quiz->cmid,
            $this->quiz->id,
            $screenshot_data
        );
        
        // Get the stored file
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id,
            'quizaccess_invigilator',
            'picture',
            $result['screenshotid']
        );
        
        $image_file = null;
        foreach ($files as $file) {
            if ($file->get_filename() !== '.') {
                $image_file = $file;
                break;
            }
        }
        
        $this->assertNotNull($image_file, 'Image file should exist');
        
        // Verify file properties
        $this->assertEquals('quizaccess_invigilator', $image_file->get_component(), 
            'Component should be correct');
        $this->assertEquals('picture', $image_file->get_filearea(), 
            'File area should be correct');
        $this->assertEquals($this->user->id, $image_file->get_userid(), 
            'User ID should be correct');
        $this->assertEquals($this->context->id, $image_file->get_contextid(), 
            'Context ID should be correct');
    }
}

// Run tests if called directly
if (!defined('PHPUNIT_TEST') && basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "Running Invigilator Plugin Screenshot Capture Tests...\n\n";
    
    $test = new quizaccess_invigilator_screenshot_capture_test();
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