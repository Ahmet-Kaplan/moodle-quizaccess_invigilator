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
 * Database tests for the quizaccess_invigilator plugin.
 *
 * @package    quizaccess_invigilator
 * @copyright  2024 Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/phpunit/classes/base_testcase.php');

/**
 * Database test class for Invigilator plugin.
 */
class quizaccess_invigilator_database_test extends advanced_testcase {

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test main plugin table structure and constraints.
     */
    public function test_main_table_structure() {
        global $DB;
        
        $dbman = $DB->get_manager();
        $table = new xmldb_table('quizaccess_invigilator');
        
        // Test table exists
        $this->assertTrue($dbman->table_exists($table), 'Main plugin table should exist');
        
        // Test primary key
        $field = new xmldb_field('id');
        $this->assertTrue($dbman->field_exists($table, $field), 'ID field should exist');
        
        // Test foreign key field
        $field = new xmldb_field('quizid');
        $this->assertTrue($dbman->field_exists($table, $field), 'Quiz ID field should exist');
        
        // Test configuration field
        $field = new xmldb_field('invigilatorrequired');
        $this->assertTrue($dbman->field_exists($table, $field), 'Invigilator required field should exist');
        
        // Test field types and constraints
        $columns = $DB->get_columns('quizaccess_invigilator');
        
        $this->assertArrayHasKey('id', $columns, 'ID column should exist');
        $this->assertArrayHasKey('quizid', $columns, 'Quiz ID column should exist');
        $this->assertArrayHasKey('invigilatorrequired', $columns, 'Invigilator required column should exist');
        
        // Test ID field is auto-increment
        $this->assertTrue($columns['id']->auto_increment, 'ID field should be auto-increment');
        
        // Test quiz ID is integer
        $this->assertEquals('int', $columns['quizid']->type, 'Quiz ID should be integer type');
    }

    /**
     * Test logs table structure and constraints.
     */
    public function test_logs_table_structure() {
        global $DB;
        
        $dbman = $DB->get_manager();
        $table = new xmldb_table('quizaccess_invigilator_logs');
        
        // Test table exists
        $this->assertTrue($dbman->table_exists($table), 'Logs table should exist');
        
        // Test required fields
        $required_fields = [
            'id', 'courseid', 'cmid', 'quizid', 'userid', 'screenshot', 'timecreated'
        ];
        
        foreach ($required_fields as $field_name) {
            $field = new xmldb_field($field_name);
            $this->assertTrue($dbman->field_exists($table, $field), 
                "Field '$field_name' should exist in logs table");
        }
        
        // Test field types
        $columns = $DB->get_columns('quizaccess_invigilator_logs');
        
        $this->assertEquals('int', $columns['id']->type, 'ID should be integer');
        $this->assertEquals('int', $columns['courseid']->type, 'Course ID should be integer');
        $this->assertEquals('int', $columns['cmid']->type, 'CM ID should be integer');
        $this->assertEquals('int', $columns['quizid']->type, 'Quiz ID should be integer');
        $this->assertEquals('int', $columns['userid']->type, 'User ID should be integer');
        $this->assertEquals('text', $columns['screenshot']->type, 'Screenshot should be text type');
        $this->assertEquals('int', $columns['timecreated']->type, 'Time created should be integer');
    }

    /**
     * Test database CRUD operations on main table.
     */
    public function test_main_table_crud_operations() {
        global $DB;
        
        // Create test data
        $record = new stdClass();
        $record->quizid = 1;
        $record->invigilatorrequired = 1;
        
        // Test INSERT
        $id = $DB->insert_record('quizaccess_invigilator', $record);
        $this->assertNotEmpty($id, 'Should be able to insert record');
        $this->assertIsInt($id, 'Insert should return integer ID');
        
        // Test SELECT
        $retrieved = $DB->get_record('quizaccess_invigilator', ['id' => $id]);
        $this->assertNotEmpty($retrieved, 'Should be able to retrieve record');
        $this->assertEquals($record->quizid, $retrieved->quizid, 'Quiz ID should match');
        $this->assertEquals($record->invigilatorrequired, $retrieved->invigilatorrequired, 
            'Invigilator required should match');
        
        // Test UPDATE
        $retrieved->invigilatorrequired = 0;
        $result = $DB->update_record('quizaccess_invigilator', $retrieved);
        $this->assertTrue($result, 'Should be able to update record');
        
        $updated = $DB->get_record('quizaccess_invigilator', ['id' => $id]);
        $this->assertEquals(0, $updated->invigilatorrequired, 'Field should be updated');
        
        // Test DELETE
        $result = $DB->delete_records('quizaccess_invigilator', ['id' => $id]);
        $this->assertTrue($result, 'Should be able to delete record');
        
        $deleted = $DB->get_record('quizaccess_invigilator', ['id' => $id]);
        $this->assertFalse($deleted, 'Record should be deleted');
    }

    /**
     * Test database CRUD operations on logs table.
     */
    public function test_logs_table_crud_operations() {
        global $DB;
        
        // Create test data
        $record = new stdClass();
        $record->courseid = 1;
        $record->cmid = 1;
        $record->quizid = 1;
        $record->userid = 2;
        $record->screenshot = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        $record->timecreated = time();
        
        // Test INSERT
        $id = $DB->insert_record('quizaccess_invigilator_logs', $record);
        $this->assertNotEmpty($id, 'Should be able to insert log record');
        
        // Test SELECT
        $retrieved = $DB->get_record('quizaccess_invigilator_logs', ['id' => $id]);
        $this->assertNotEmpty($retrieved, 'Should be able to retrieve log record');
        $this->assertEquals($record->courseid, $retrieved->courseid, 'Course ID should match');
        $this->assertEquals($record->quizid, $retrieved->quizid, 'Quiz ID should match');
        $this->assertEquals($record->userid, $retrieved->userid, 'User ID should match');
        $this->assertStringContainsString('data:image/png', $retrieved->screenshot, 
            'Screenshot data should be preserved');
        
        // Test UPDATE
        $retrieved->screenshot = 'updated_screenshot_data';
        $result = $DB->update_record('quizaccess_invigilator_logs', $retrieved);
        $this->assertTrue($result, 'Should be able to update log record');
        
        // Test DELETE
        $result = $DB->delete_records('quizaccess_invigilator_logs', ['id' => $id]);
        $this->assertTrue($result, 'Should be able to delete log record');
    }

    /**
     * Test database constraints and data integrity.
     */
    public function test_database_constraints() {
        global $DB;
        
        // Test unique constraint on quizid in main table
        $record1 = new stdClass();
        $record1->quizid = 999;
        $record1->invigilatorrequired = 1;
        
        $id1 = $DB->insert_record('quizaccess_invigilator', $record1);
        $this->assertNotEmpty($id1, 'First record should be inserted');
        
        // Try to insert duplicate quizid (should fail due to unique constraint)
        $record2 = new stdClass();
        $record2->quizid = 999;
        $record2->invigilatorrequired = 0;
        
        try {
            $DB->insert_record('quizaccess_invigilator', $record2);
            $this->fail('Should not be able to insert duplicate quiz ID');
        } catch (dml_exception $e) {
            $this->assertTrue(true, 'Duplicate quiz ID should be rejected');
        }
        
        // Clean up
        $DB->delete_records('quizaccess_invigilator', ['id' => $id1]);
    }

    /**
     * Test database performance with multiple records.
     */
    public function test_database_performance() {
        global $DB;
        
        $start_time = microtime(true);
        
        // Insert multiple records
        $records = [];
        for ($i = 1; $i <= 100; $i++) {
            $record = new stdClass();
            $record->courseid = 1;
            $record->cmid = 1;
            $record->quizid = 1;
            $record->userid = $i;
            $record->screenshot = "test_screenshot_data_$i";
            $record->timecreated = time() + $i;
            
            $records[] = $record;
        }
        
        // Batch insert
        foreach ($records as $record) {
            $DB->insert_record('quizaccess_invigilator_logs', $record);
        }
        
        $insert_time = microtime(true) - $start_time;
        
        // Test retrieval performance
        $start_time = microtime(true);
        $retrieved_records = $DB->get_records('quizaccess_invigilator_logs', ['quizid' => 1]);
        $select_time = microtime(true) - $start_time;
        
        $this->assertCount(100, $retrieved_records, 'Should retrieve all inserted records');
        $this->assertLessThan(5.0, $insert_time, 'Insert operations should complete within 5 seconds');
        $this->assertLessThan(1.0, $select_time, 'Select operations should complete within 1 second');
        
        // Clean up
        $DB->delete_records('quizaccess_invigilator_logs', ['quizid' => 1]);
    }

    /**
     * Test database indexes and query optimization.
     */
    public function test_database_indexes() {
        global $DB;
        
        // Insert test data
        $records = [];
        for ($i = 1; $i <= 50; $i++) {
            $record = new stdClass();
            $record->courseid = ($i % 5) + 1;
            $record->cmid = ($i % 3) + 1;
            $record->quizid = ($i % 10) + 1;
            $record->userid = $i;
            $record->screenshot = "test_data_$i";
            $record->timecreated = time() + $i;
            
            $records[] = $record;
            $DB->insert_record('quizaccess_invigilator_logs', $record);
        }
        
        // Test queries that should benefit from indexes
        $start_time = microtime(true);
        
        // Query by quiz ID (should be indexed)
        $quiz_records = $DB->get_records('quizaccess_invigilator_logs', ['quizid' => 1]);
        $this->assertNotEmpty($quiz_records, 'Should find records by quiz ID');
        
        // Query by user ID (should be indexed)
        $user_records = $DB->get_records('quizaccess_invigilator_logs', ['userid' => 10]);
        $this->assertNotEmpty($user_records, 'Should find records by user ID');
        
        // Query by course ID
        $course_records = $DB->get_records('quizaccess_invigilator_logs', ['courseid' => 2]);
        $this->assertNotEmpty($course_records, 'Should find records by course ID');
        
        $query_time = microtime(true) - $start_time;
        $this->assertLessThan(1.0, $query_time, 'Indexed queries should be fast');
        
        // Clean up
        $DB->delete_records('quizaccess_invigilator_logs');
    }

    /**
     * Test database transaction handling.
     */
    public function test_database_transactions() {
        global $DB;
        
        // Test successful transaction
        $transaction = $DB->start_delegated_transaction();
        
        $record1 = new stdClass();
        $record1->quizid = 100;
        $record1->invigilatorrequired = 1;
        $id1 = $DB->insert_record('quizaccess_invigilator', $record1);
        
        $record2 = new stdClass();
        $record2->courseid = 1;
        $record2->cmid = 1;
        $record2->quizid = 100;
        $record2->userid = 1;
        $record2->screenshot = 'transaction_test';
        $record2->timecreated = time();
        $id2 = $DB->insert_record('quizaccess_invigilator_logs', $record2);
        
        $transaction->allow_commit();
        
        // Verify records exist after commit
        $this->assertTrue($DB->record_exists('quizaccess_invigilator', ['id' => $id1]), 
            'Record should exist after successful transaction');
        $this->assertTrue($DB->record_exists('quizaccess_invigilator_logs', ['id' => $id2]), 
            'Log record should exist after successful transaction');
        
        // Test rollback transaction
        $transaction = $DB->start_delegated_transaction();
        
        $record3 = new stdClass();
        $record3->quizid = 101;
        $record3->invigilatorrequired = 1;
        $id3 = $DB->insert_record('quizaccess_invigilator', $record3);
        
        // Rollback without commit
        $transaction->rollback(new Exception('Test rollback'));
        
        // Verify record doesn't exist after rollback
        $this->assertFalse($DB->record_exists('quizaccess_invigilator', ['id' => $id3]), 
            'Record should not exist after rollback');
        
        // Clean up committed records
        $DB->delete_records('quizaccess_invigilator', ['id' => $id1]);
        $DB->delete_records('quizaccess_invigilator_logs', ['id' => $id2]);
    }
}

// Run tests if called directly
if (!defined('PHPUNIT_TEST') && basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "Running Invigilator Plugin Database Tests...\n\n";
    
    $test = new quizaccess_invigilator_database_test();
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