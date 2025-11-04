<?php
/**
 * Plugin Activation Verification Script
 * 
 * This script verifies that the plugin can be properly activated and integrated with Moodle
 *
 * @package    quizaccess_invigilator
 * @copyright  2024 Testing Environment
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Check if we're running from the correct location
$moodle_root = getenv('MOODLE_ROOT') ?: '/var/www/html';
$config_path = $moodle_root . '/config.php';

if (!file_exists($config_path)) {
    echo "Error: Moodle config.php not found at: $config_path\n";
    echo "Please set MOODLE_ROOT environment variable or run from Moodle directory\n";
    exit(1);
}

// Include Moodle configuration
require_once($config_path);
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/adminlib.php');

// CLI options
list($options, $unrecognized) = cli_get_params(
    array(
        'help' => false,
        'verbose' => false,
        'check-installation' => false,
        'check-capabilities' => false,
        'check-services' => false,
        'check-integration' => false,
        'test-quiz-access' => false
    ),
    array(
        'h' => 'help',
        'v' => 'verbose'
    )
);

if ($options['help']) {
    $help = "
Plugin Activation Verification Script for Invigilator Plugin

Options:
-h, --help              Print out this help
-v, --verbose           Verbose output
--check-installation    Check if plugin is properly installed
--check-capabilities    Verify capabilities are registered
--check-services        Verify web services are registered
--check-integration     Check Moodle integration points
--test-quiz-access      Test quiz access rule integration

Example:
\$sudo -u www-data /usr/bin/php {$argv[0]} --check-installation --verbose
";
    echo $help;
    exit(0);
}

/**
 * Activation verification class
 */
class ActivationValidator {
    
    private $verbose;
    private $db;
    
    public function __construct($verbose = false) {
        global $DB;
        
        $this->verbose = $verbose;
        $this->db = $DB;
        
        $this->log("Starting plugin activation verification...");
    }
    
    /**
     * Log message with optional verbose mode
     */
    private function log($message, $verbose_only = false) {
        if (!$verbose_only || $this->verbose) {
            echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        }
    }
    
    /**
     * Check if plugin is installed in database
     */
    public function check_plugin_installation() {
        $this->log("Checking plugin installation status...");
        
        try {
            // Check plugin version in database
            $plugin_record = $this->db->get_record('config_plugins', 
                array('plugin' => 'quizaccess_invigilator', 'name' => 'version'));
            
            if ($plugin_record) {
                $this->log("✓ Plugin is installed in database");
                $this->log("  Version: " . $plugin_record->value, true);
                
                // Check additional plugin settings
                $settings = $this->db->get_records('config_plugins', 
                    array('plugin' => 'quizaccess_invigilator'));
                
                $this->log("  Found " . count($settings) . " plugin settings", true);
                
                if ($this->verbose) {
                    foreach ($settings as $setting) {
                        $this->log("    {$setting->name}: {$setting->value}", true);
                    }
                }
                
                return true;
            } else {
                $this->log("✗ Plugin not found in database");
                return false;
            }
            
        } catch (Exception $e) {
            $this->log("ERROR: Database check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if plugin tables exist and have correct structure
     */
    public function check_plugin_tables() {
        $this->log("Checking plugin database tables...");
        
        $expected_tables = [
            'quizaccess_invigilator' => [
                'id', 'quizid', 'invigilatorrequired'
            ],
            'quizaccess_invigilator_logs' => [
                'id', 'courseid', 'cmid', 'quizid', 'userid', 'screenshot', 'timecreated'
            ]
        ];
        
        $all_tables_ok = true;
        
        foreach ($expected_tables as $table_name => $expected_columns) {
            try {
                // Check if table exists
                if (!$this->db->get_manager()->table_exists($table_name)) {
                    $this->log("✗ Table missing: $table_name");
                    $all_tables_ok = false;
                    continue;
                }
                
                $this->log("✓ Table exists: $table_name");
                
                // Check table columns
                $columns = $this->db->get_columns($table_name);
                $column_names = array_keys($columns);
                
                $missing_columns = array_diff($expected_columns, $column_names);
                $extra_columns = array_diff($column_names, $expected_columns);
                
                if (empty($missing_columns) && empty($extra_columns)) {
                    $this->log("  ✓ Table structure is correct", true);
                } else {
                    if (!empty($missing_columns)) {
                        $this->log("  ✗ Missing columns: " . implode(', ', $missing_columns));
                        $all_tables_ok = false;
                    }
                    
                    if (!empty($extra_columns)) {
                        $this->log("  ! Extra columns: " . implode(', ', $extra_columns), true);
                    }
                }
                
                // Check if table has data (optional)
                $record_count = $this->db->count_records($table_name);
                $this->log("  Records in table: $record_count", true);
                
            } catch (Exception $e) {
                $this->log("ERROR: Failed to check table $table_name: " . $e->getMessage());
                $all_tables_ok = false;
            }
        }
        
        return $all_tables_ok;
    }
    
    /**
     * Check if capabilities are properly registered
     */
    public function check_capabilities() {
        $this->log("Checking plugin capabilities...");
        
        $expected_capabilities = [
            'quizaccess/invigilator:sendscreenshot',
            'quizaccess/invigilator:getscreenshot',
            'quizaccess/invigilator:viewreport',
            'quizaccess/invigilator:deletescreenshot'
        ];
        
        $all_capabilities_ok = true;
        
        foreach ($expected_capabilities as $capability) {
            try {
                // Check if capability exists in database
                $cap_record = $this->db->get_record('capabilities', array('name' => $capability));
                
                if ($cap_record) {
                    $this->log("✓ Capability registered: $capability");
                    $this->log("  Component: {$cap_record->component}", true);
                    $this->log("  Context level: {$cap_record->contextlevel}", true);
                    $this->log("  Risk bitmask: {$cap_record->riskbitmask}", true);
                } else {
                    $this->log("✗ Capability not registered: $capability");
                    $all_capabilities_ok = false;
                }
                
            } catch (Exception $e) {
                $this->log("ERROR: Failed to check capability $capability: " . $e->getMessage());
                $all_capabilities_ok = false;
            }
        }
        
        return $all_capabilities_ok;
    }
    
    /**
     * Check if web services are properly registered
     */
    public function check_web_services() {
        $this->log("Checking plugin web services...");
        
        $expected_functions = [
            'quizaccess_invigilator_send_screenshot'
        ];
        
        $all_services_ok = true;
        
        foreach ($expected_functions as $function_name) {
            try {
                // Check if external function exists
                $function_record = $this->db->get_record('external_functions', 
                    array('name' => $function_name));
                
                if ($function_record) {
                    $this->log("✓ Web service registered: $function_name");
                    $this->log("  Class: {$function_record->classname}", true);
                    $this->log("  Method: {$function_record->methodname}", true);
                    $this->log("  Component: {$function_record->component}", true);
                } else {
                    $this->log("! Web service not registered: $function_name (may be optional)");
                    // Don't mark as failure since web services might be optional
                }
                
            } catch (Exception $e) {
                $this->log("ERROR: Failed to check web service $function_name: " . $e->getMessage());
                $all_services_ok = false;
            }
        }
        
        return $all_services_ok;
    }
    
    /**
     * Check Moodle integration points
     */
    public function check_moodle_integration() {
        $this->log("Checking Moodle integration points...");
        
        $integration_ok = true;
        
        try {
            // Check if plugin manager recognizes the plugin
            $pluginman = core_plugin_manager::instance();
            $plugin_info = $pluginman->get_plugin_info('quizaccess_invigilator');
            
            if ($plugin_info) {
                $this->log("✓ Plugin recognized by plugin manager");
                $this->log("  Display name: {$plugin_info->displayname}", true);
                $this->log("  Version: {$plugin_info->versiondb}", true);
                $this->log("  Status: {$plugin_info->get_status()}", true);
                
                // Check if plugin is enabled
                if ($plugin_info->is_enabled()) {
                    $this->log("✓ Plugin is enabled");
                } else {
                    $this->log("✗ Plugin is disabled");
                    $integration_ok = false;
                }
                
            } else {
                $this->log("✗ Plugin not recognized by plugin manager");
                $integration_ok = false;
            }
            
        } catch (Exception $e) {
            $this->log("ERROR: Plugin manager check failed: " . $e->getMessage());
            $integration_ok = false;
        }
        
        // Check if quiz access rule is available
        try {
            $access_rules = quiz_access_manager::get_rule_classes();
            
            if (in_array('quizaccess_invigilator', $access_rules)) {
                $this->log("✓ Quiz access rule is registered");
            } else {
                $this->log("✗ Quiz access rule not found in available rules");
                $this->log("  Available rules: " . implode(', ', $access_rules), true);
                $integration_ok = false;
            }
            
        } catch (Exception $e) {
            $this->log("ERROR: Quiz access rule check failed: " . $e->getMessage());
            $integration_ok = false;
        }
        
        return $integration_ok;
    }
    
    /**
     * Test quiz access rule functionality
     */
    public function test_quiz_access_rule() {
        $this->log("Testing quiz access rule functionality...");
        
        try {
            // Try to instantiate the rule class
            $rule_file = $CFG->dirroot . '/mod/quiz/accessrule/invigilator/rule.php';
            
            if (!file_exists($rule_file)) {
                $this->log("✗ Rule class file not found");
                return false;
            }
            
            require_once($rule_file);
            
            if (!class_exists('quizaccess_invigilator')) {
                $this->log("✗ Rule class not found");
                return false;
            }
            
            $this->log("✓ Rule class exists and can be loaded");
            
            // Check if class extends proper base class
            $reflection = new ReflectionClass('quizaccess_invigilator');
            $parent_class = $reflection->getParentClass();
            
            if ($parent_class && $parent_class->getName() === 'quiz_access_rule_base') {
                $this->log("✓ Rule class extends proper base class");
            } else {
                $this->log("✗ Rule class does not extend quiz_access_rule_base");
                return false;
            }
            
            // Check for required methods
            $required_methods = [
                'is_preflight_check_required',
                'add_preflight_check_form_fields'
            ];
            
            $methods_ok = true;
            foreach ($required_methods as $method) {
                if ($reflection->hasMethod($method)) {
                    $this->log("  ✓ Method exists: $method", true);
                } else {
                    $this->log("  ✗ Method missing: $method");
                    $methods_ok = false;
                }
            }
            
            return $methods_ok;
            
        } catch (Exception $e) {
            $this->log("ERROR: Quiz access rule test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Run all activation checks
     */
    public function run_all_checks() {
        $this->log("Running comprehensive activation verification...");
        
        $checks = [
            'installation' => $this->check_plugin_installation(),
            'tables' => $this->check_plugin_tables(),
            'capabilities' => $this->check_capabilities(),
            'services' => $this->check_web_services(),
            'integration' => $this->check_moodle_integration(),
            'quiz_access' => $this->test_quiz_access_rule()
        ];
        
        $this->log("\n=== ACTIVATION VERIFICATION SUMMARY ===");
        
        $passed = 0;
        $total = count($checks);
        
        foreach ($checks as $check_name => $result) {
            $status = $result ? '✓ PASSED' : '✗ FAILED';
            $this->log("$status: " . str_replace('_', ' ', ucfirst($check_name)));
            
            if ($result) {
                $passed++;
            }
        }
        
        $overall = $passed === $total;
        $status = $overall ? 'PASSED' : 'FAILED';
        
        $this->log("\nOVERALL: $passed/$total checks passed - $status");
        
        if ($overall) {
            $this->log("✓ Plugin is properly activated and integrated with Moodle");
        } else {
            $this->log("✗ Plugin has activation issues that need to be addressed");
        }
        
        return $overall;
    }
}

// Main execution
$validator = new ActivationValidator($options['verbose']);

// Determine which checks to run
$any_check = false;

if ($options['check-installation']) {
    $validator->check_plugin_installation();
    $validator->check_plugin_tables();
    $any_check = true;
}

if ($options['check-capabilities']) {
    $validator->check_capabilities();
    $any_check = true;
}

if ($options['check-services']) {
    $validator->check_web_services();
    $any_check = true;
}

if ($options['check-integration']) {
    $validator->check_moodle_integration();
    $any_check = true;
}

if ($options['test-quiz-access']) {
    $validator->test_quiz_access_rule();
    $any_check = true;
}

// If no specific checks requested, run all
if (!$any_check) {
    $success = $validator->run_all_checks();
    exit($success ? 0 : 1);
}

exit(0);