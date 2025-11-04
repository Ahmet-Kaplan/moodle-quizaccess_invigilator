<?php
/**
 * Moodle 4.4.3 Compatibility Validation Script
 * 
 * This script validates the invigilator plugin's compatibility with Moodle 4.4.3
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

// CLI options
list($options, $unrecognized) = cli_get_params(
    array(
        'help' => false,
        'verbose' => false,
        'plugin-path' => '',
        'check-all' => false,
        'check-api' => false,
        'check-db' => false,
        'check-capabilities' => false,
        'check-services' => false
    ),
    array(
        'h' => 'help',
        'v' => 'verbose',
        'p' => 'plugin-path',
        'a' => 'check-all'
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "
Moodle 4.4.3 Compatibility Validation Script for Invigilator Plugin

Options:
-h, --help              Print out this help
-v, --verbose           Verbose output
-p, --plugin-path       Path to plugin directory (default: auto-detect)
-a, --check-all         Run all compatibility checks
--check-api             Check API compatibility
--check-db              Check database schema compatibility
--check-capabilities    Check capabilities compatibility
--check-services        Check web services compatibility

Example:
\$sudo -u www-data /usr/bin/php {$argv[0]} --check-all --verbose
";
    echo $help;
    exit(0);
}

/**
 * Compatibility validation class
 */
class MoodleCompatibilityValidator {
    
    private $verbose;
    private $plugin_path;
    private $moodle_version;
    private $target_version = '2024042200'; // Moodle 4.4.3
    private $results = [];
    
    public function __construct($verbose = false, $plugin_path = '') {
        global $CFG;
        
        $this->verbose = $verbose;
        $this->moodle_version = $CFG->version;
        
        // Auto-detect plugin path if not provided
        if (empty($plugin_path)) {
            $this->plugin_path = $CFG->dirroot . '/mod/quiz/accessrule/invigilator';
        } else {
            $this->plugin_path = $plugin_path;
        }
        
        $this->log("Moodle version: " . $CFG->version . " (" . $CFG->release . ")");
        $this->log("Plugin path: " . $this->plugin_path);
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
     * Add result to results array
     */
    private function add_result($category, $check, $status, $message, $details = '') {
        $this->results[] = [
            'category' => $category,
            'check' => $check,
            'status' => $status,
            'message' => $message,
            'details' => $details
        ];
        
        $status_symbol = $status ? '✓' : '✗';
        $this->log("$status_symbol $category: $check - $message");
        
        if (!empty($details) && $this->verbose) {
            $this->log("  Details: $details", true);
        }
    }
    
    /**
     * Check if plugin files exist
     */
    public function check_plugin_files() {
        $this->log("Checking plugin file structure...");
        
        $required_files = [
            'version.php' => 'Plugin version file',
            'rule.php' => 'Main rule class file',
            'db/install.xml' => 'Database schema file',
            'db/access.php' => 'Capabilities definition file',
            'lang/en/quizaccess_invigilator.php' => 'English language file'
        ];
        
        $all_exist = true;
        
        foreach ($required_files as $file => $description) {
            $file_path = $this->plugin_path . '/' . $file;
            $exists = file_exists($file_path);
            
            $this->add_result(
                'File Structure',
                $description,
                $exists,
                $exists ? 'File exists' : 'File missing',
                $file_path
            );
            
            if (!$exists) {
                $all_exist = false;
            }
        }
        
        return $all_exist;
    }
    
    /**
     * Check Moodle version compatibility
     */
    public function check_version_compatibility() {
        $this->log("Checking version compatibility...");
        
        $version_file = $this->plugin_path . '/version.php';
        
        if (!file_exists($version_file)) {
            $this->add_result(
                'Version',
                'Version file check',
                false,
                'version.php not found'
            );
            return false;
        }
        
        // Parse version.php
        $plugin = new stdClass();
        include($version_file);
        
        // Check required Moodle version
        $required_version = isset($plugin->requires) ? $plugin->requires : 0;
        $compatible = $this->moodle_version >= $required_version;
        
        $this->add_result(
            'Version',
            'Moodle version requirement',
            $compatible,
            $compatible ? 'Compatible' : 'Incompatible',
            "Required: $required_version, Current: {$this->moodle_version}"
        );
        
        // Check if plugin version is reasonable for Moodle 4.4.3
        $plugin_version = isset($plugin->version) ? $plugin->version : 0;
        $version_reasonable = $plugin_version > 2019000000; // Should be newer than 2019
        
        $this->add_result(
            'Version',
            'Plugin version check',
            $version_reasonable,
            $version_reasonable ? 'Version looks reasonable' : 'Version may be outdated',
            "Plugin version: $plugin_version"
        );
        
        return $compatible && $version_reasonable;
    }
    
    /**
     * Check database schema compatibility
     */
    public function check_database_compatibility() {
        $this->log("Checking database schema compatibility...");
        
        $install_xml = $this->plugin_path . '/db/install.xml';
        
        if (!file_exists($install_xml)) {
            $this->add_result(
                'Database',
                'Schema file check',
                false,
                'install.xml not found'
            );
            return false;
        }
        
        // Validate XML syntax
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($install_xml);
        $xml_errors = libxml_get_errors();
        
        if ($xml === false || !empty($xml_errors)) {
            $error_messages = [];
            foreach ($xml_errors as $error) {
                $error_messages[] = trim($error->message);
            }
            
            $this->add_result(
                'Database',
                'XML syntax validation',
                false,
                'Invalid XML syntax',
                implode('; ', $error_messages)
            );
            return false;
        }
        
        $this->add_result(
            'Database',
            'XML syntax validation',
            true,
            'Valid XML syntax'
        );
        
        // Check XMLDB structure
        $has_xmldb = isset($xml['PATH']) && isset($xml['VERSION']);
        $this->add_result(
            'Database',
            'XMLDB structure',
            $has_xmldb,
            $has_xmldb ? 'Valid XMLDB structure' : 'Invalid XMLDB structure'
        );
        
        // Check for required tables
        $tables = $xml->xpath('//TABLE');
        $required_tables = ['quizaccess_invigilator', 'quizaccess_invigilator_logs'];
        $tables_found = [];
        
        foreach ($tables as $table) {
            $table_name = (string)$table['NAME'];
            $tables_found[] = $table_name;
        }
        
        foreach ($required_tables as $required_table) {
            $found = in_array($required_table, $tables_found);
            $this->add_result(
                'Database',
                "Table: $required_table",
                $found,
                $found ? 'Table defined' : 'Table missing'
            );
        }
        
        return true;
    }
    
    /**
     * Check capabilities compatibility
     */
    public function check_capabilities_compatibility() {
        $this->log("Checking capabilities compatibility...");
        
        $access_file = $this->plugin_path . '/db/access.php';
        
        if (!file_exists($access_file)) {
            $this->add_result(
                'Capabilities',
                'Access file check',
                false,
                'access.php not found'
            );
            return false;
        }
        
        // Include and parse access.php
        $capabilities = [];
        include($access_file);
        
        if (empty($capabilities)) {
            $this->add_result(
                'Capabilities',
                'Capabilities definition',
                false,
                'No capabilities defined'
            );
            return false;
        }
        
        $this->add_result(
            'Capabilities',
            'Capabilities definition',
            true,
            count($capabilities) . ' capabilities defined'
        );
        
        // Check each capability structure
        $valid_capabilities = true;
        
        foreach ($capabilities as $capability => $definition) {
            $has_captype = isset($definition['captype']);
            $has_contextlevel = isset($definition['contextlevel']);
            $has_archetypes = isset($definition['archetypes']);
            
            $capability_valid = $has_captype && $has_contextlevel && $has_archetypes;
            
            $this->add_result(
                'Capabilities',
                "Capability: $capability",
                $capability_valid,
                $capability_valid ? 'Valid structure' : 'Invalid structure',
                $capability_valid ? '' : 'Missing required fields'
            );
            
            if (!$capability_valid) {
                $valid_capabilities = false;
            }
        }
        
        return $valid_capabilities;
    }
    
    /**
     * Check web services compatibility
     */
    public function check_services_compatibility() {
        $this->log("Checking web services compatibility...");
        
        $services_file = $this->plugin_path . '/db/services.php';
        
        if (!file_exists($services_file)) {
            $this->add_result(
                'Services',
                'Services file check',
                false,
                'services.php not found'
            );
            return false;
        }
        
        // Include and parse services.php
        $functions = [];
        include($services_file);
        
        if (empty($functions)) {
            $this->add_result(
                'Services',
                'Functions definition',
                true,
                'No web services defined (optional)'
            );
            return true;
        }
        
        $this->add_result(
            'Services',
            'Functions definition',
            true,
            count($functions) . ' functions defined'
        );
        
        // Check each function structure
        $valid_functions = true;
        
        foreach ($functions as $function_name => $definition) {
            $has_classname = isset($definition['classname']);
            $has_methodname = isset($definition['methodname']);
            $has_description = isset($definition['description']);
            $has_type = isset($definition['type']);
            
            $function_valid = $has_classname && $has_methodname && $has_description && $has_type;
            
            $this->add_result(
                'Services',
                "Function: $function_name",
                $function_valid,
                $function_valid ? 'Valid structure' : 'Invalid structure'
            );
            
            if (!$function_valid) {
                $valid_functions = false;
            }
        }
        
        return $valid_functions;
    }
    
    /**
     * Check API compatibility
     */
    public function check_api_compatibility() {
        $this->log("Checking API compatibility...");
        
        // Check if main rule class exists and follows proper structure
        $rule_file = $this->plugin_path . '/rule.php';
        
        if (!file_exists($rule_file)) {
            $this->add_result(
                'API',
                'Rule class file',
                false,
                'rule.php not found'
            );
            return false;
        }
        
        // Check PHP syntax
        $syntax_check = shell_exec("php -l '$rule_file' 2>&1");
        $syntax_valid = strpos($syntax_check, 'No syntax errors') !== false;
        
        $this->add_result(
            'API',
            'PHP syntax check',
            $syntax_valid,
            $syntax_valid ? 'Valid PHP syntax' : 'Syntax errors found',
            $syntax_valid ? '' : $syntax_check
        );
        
        if (!$syntax_valid) {
            return false;
        }
        
        // Check class structure (basic check)
        $rule_content = file_get_contents($rule_file);
        
        $has_class = preg_match('/class\s+quizaccess_invigilator\s+extends\s+quiz_access_rule_base/', $rule_content);
        $this->add_result(
            'API',
            'Rule class structure',
            $has_class,
            $has_class ? 'Proper class inheritance' : 'Invalid class structure'
        );
        
        // Check for required methods (basic check)
        $required_methods = ['is_preflight_check_required', 'add_preflight_check_form_fields'];
        $methods_found = 0;
        
        foreach ($required_methods as $method) {
            $has_method = strpos($rule_content, "function $method") !== false || 
                         strpos($rule_content, "public function $method") !== false ||
                         strpos($rule_content, "public static function $method") !== false;
            
            $this->add_result(
                'API',
                "Method: $method",
                $has_method,
                $has_method ? 'Method found' : 'Method missing'
            );
            
            if ($has_method) {
                $methods_found++;
            }
        }
        
        return $syntax_valid && $has_class && $methods_found > 0;
    }
    
    /**
     * Run all compatibility checks
     */
    public function run_all_checks() {
        $this->log("Starting comprehensive compatibility validation...");
        
        $checks = [
            'files' => $this->check_plugin_files(),
            'version' => $this->check_version_compatibility(),
            'database' => $this->check_database_compatibility(),
            'capabilities' => $this->check_capabilities_compatibility(),
            'services' => $this->check_services_compatibility(),
            'api' => $this->check_api_compatibility()
        ];
        
        return $checks;
    }
    
    /**
     * Generate summary report
     */
    public function generate_summary() {
        $this->log("\n=== COMPATIBILITY VALIDATION SUMMARY ===");
        
        $categories = [];
        $total_checks = 0;
        $passed_checks = 0;
        
        foreach ($this->results as $result) {
            $category = $result['category'];
            
            if (!isset($categories[$category])) {
                $categories[$category] = ['total' => 0, 'passed' => 0];
            }
            
            $categories[$category]['total']++;
            $total_checks++;
            
            if ($result['status']) {
                $categories[$category]['passed']++;
                $passed_checks++;
            }
        }
        
        foreach ($categories as $category => $stats) {
            $percentage = $stats['total'] > 0 ? round(($stats['passed'] / $stats['total']) * 100, 1) : 0;
            $status = $stats['passed'] === $stats['total'] ? '✓' : '✗';
            
            $this->log("$status $category: {$stats['passed']}/{$stats['total']} ($percentage%)");
        }
        
        $overall_percentage = $total_checks > 0 ? round(($passed_checks / $total_checks) * 100, 1) : 0;
        $overall_status = $passed_checks === $total_checks ? 'PASSED' : 'FAILED';
        
        $this->log("\nOVERALL: $passed_checks/$total_checks checks passed ($overall_percentage%) - $overall_status");
        
        if ($overall_status === 'PASSED') {
            $this->log("✓ Plugin appears to be compatible with Moodle 4.4.3");
        } else {
            $this->log("✗ Plugin has compatibility issues that need to be addressed");
        }
        
        return $overall_status === 'PASSED';
    }
}

// Main execution
$validator = new MoodleCompatibilityValidator($options['verbose'], $options['plugin-path']);

// Determine which checks to run
if ($options['check-all']) {
    $validator->run_all_checks();
} else {
    $any_check = false;
    
    if ($options['check-api']) {
        $validator->check_api_compatibility();
        $any_check = true;
    }
    
    if ($options['check-db']) {
        $validator->check_database_compatibility();
        $any_check = true;
    }
    
    if ($options['check-capabilities']) {
        $validator->check_capabilities_compatibility();
        $any_check = true;
    }
    
    if ($options['check-services']) {
        $validator->check_services_compatibility();
        $any_check = true;
    }
    
    // If no specific checks requested, run all
    if (!$any_check) {
        $validator->run_all_checks();
    }
}

// Generate summary and exit with appropriate code
$success = $validator->generate_summary();
exit($success ? 0 : 1);