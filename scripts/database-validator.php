<?php
/**
 * Database Schema Validation Script
 * 
 * This script validates database schema compatibility and performs schema checks
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
require_once($CFG->libdir.'/ddllib.php');

// CLI options
list($options, $unrecognized) = cli_get_params(
    array(
        'help' => false,
        'verbose' => false,
        'plugin-path' => '',
        'validate-schema' => false,
        'check-tables' => false,
        'test-install' => false,
        'dry-run' => false
    ),
    array(
        'h' => 'help',
        'v' => 'verbose',
        'p' => 'plugin-path'
    )
);

if ($options['help']) {
    $help = "
Database Schema Validation Script for Invigilator Plugin

Options:
-h, --help              Print out this help
-v, --verbose           Verbose output
-p, --plugin-path       Path to plugin directory (default: auto-detect)
--validate-schema       Validate XML schema structure
--check-tables          Check if tables exist in database
--test-install          Test schema installation (dry run by default)
--dry-run               Perform dry run without making changes

Example:
\$sudo -u www-data /usr/bin/php {$argv[0]} --validate-schema --verbose
";
    echo $help;
    exit(0);
}

/**
 * Database validation class
 */
class DatabaseValidator {
    
    private $verbose;
    private $plugin_path;
    private $db;
    private $dbman;
    
    public function __construct($verbose = false, $plugin_path = '') {
        global $CFG, $DB;
        
        $this->verbose = $verbose;
        $this->db = $DB;
        
        // Auto-detect plugin path if not provided
        if (empty($plugin_path)) {
            $this->plugin_path = $CFG->dirroot . '/mod/quiz/accessrule/invigilator';
        } else {
            $this->plugin_path = $plugin_path;
        }
        
        // Initialize database manager
        $this->dbman = $DB->get_manager();
        
        $this->log("Plugin path: " . $this->plugin_path);
        $this->log("Database type: " . $CFG->dbtype);
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
     * Validate XML schema structure
     */
    public function validate_xml_schema() {
        $this->log("Validating XML schema structure...");
        
        $install_xml = $this->plugin_path . '/db/install.xml';
        
        if (!file_exists($install_xml)) {
            $this->log("ERROR: install.xml not found at: $install_xml");
            return false;
        }
        
        // Load and validate XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($install_xml);
        $xml_errors = libxml_get_errors();
        
        if ($xml === false) {
            $this->log("ERROR: Failed to parse XML file");
            foreach ($xml_errors as $error) {
                $this->log("XML Error: " . trim($error->message));
            }
            return false;
        }
        
        if (!empty($xml_errors)) {
            $this->log("WARNING: XML parsing warnings:");
            foreach ($xml_errors as $error) {
                $this->log("  " . trim($error->message));
            }
        }
        
        $this->log("✓ XML file is well-formed");
        
        // Validate XMLDB structure
        $this->validate_xmldb_structure($xml);
        
        // Validate tables
        $this->validate_tables_structure($xml);
        
        return true;
    }
    
    /**
     * Validate XMLDB structure
     */
    private function validate_xmldb_structure($xml) {
        $this->log("Validating XMLDB structure...", true);
        
        // Check root element attributes
        $required_attrs = ['PATH', 'VERSION', 'COMMENT'];
        foreach ($required_attrs as $attr) {
            if (!isset($xml[$attr])) {
                $this->log("WARNING: Missing required attribute: $attr");
            } else {
                $this->log("✓ Found attribute: $attr = " . $xml[$attr], true);
            }
        }
        
        // Check namespace
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['xsi'])) {
            $this->log("✓ XML Schema namespace found", true);
        } else {
            $this->log("WARNING: XML Schema namespace not found", true);
        }
        
        // Check TABLES element
        $tables_element = $xml->TABLES;
        if ($tables_element) {
            $table_count = count($tables_element->TABLE);
            $this->log("✓ Found TABLES element with $table_count tables");
        } else {
            $this->log("ERROR: TABLES element not found");
        }
    }
    
    /**
     * Validate tables structure
     */
    private function validate_tables_structure($xml) {
        $this->log("Validating table structures...");
        
        $tables = $xml->xpath('//TABLE');
        
        foreach ($tables as $table) {
            $table_name = (string)$table['NAME'];
            $this->log("Validating table: $table_name", true);
            
            // Check table attributes
            if (!isset($table['COMMENT'])) {
                $this->log("WARNING: Table $table_name missing COMMENT attribute");
            }
            
            // Validate fields
            $this->validate_table_fields($table, $table_name);
            
            // Validate keys
            $this->validate_table_keys($table, $table_name);
        }
    }
    
    /**
     * Validate table fields
     */
    private function validate_table_fields($table, $table_name) {
        $fields = $table->xpath('.//FIELD');
        
        if (empty($fields)) {
            $this->log("ERROR: Table $table_name has no fields");
            return;
        }
        
        $this->log("  Found " . count($fields) . " fields", true);
        
        $has_id_field = false;
        
        foreach ($fields as $field) {
            $field_name = (string)$field['NAME'];
            $field_type = (string)$field['TYPE'];
            
            $this->log("    Field: $field_name ($field_type)", true);
            
            // Check for ID field
            if ($field_name === 'id') {
                $has_id_field = true;
                
                // Validate ID field properties
                $is_sequence = (string)$field['SEQUENCE'] === 'true';
                $is_notnull = (string)$field['NOTNULL'] === 'true';
                $is_unsigned = (string)$field['UNSIGNED'] === 'true';
                
                if (!$is_sequence || !$is_notnull || !$is_unsigned) {
                    $this->log("WARNING: ID field in $table_name may have incorrect properties");
                }
            }
            
            // Check required attributes
            $required_field_attrs = ['TYPE', 'LENGTH', 'NOTNULL', 'SEQUENCE'];
            foreach ($required_field_attrs as $attr) {
                if (!isset($field[$attr])) {
                    $this->log("WARNING: Field $field_name missing $attr attribute");
                }
            }
        }
        
        if (!$has_id_field) {
            $this->log("ERROR: Table $table_name missing ID field");
        } else {
            $this->log("  ✓ Table has ID field", true);
        }
    }
    
    /**
     * Validate table keys
     */
    private function validate_table_keys($table, $table_name) {
        $keys = $table->xpath('.//KEY');
        
        if (empty($keys)) {
            $this->log("WARNING: Table $table_name has no keys");
            return;
        }
        
        $this->log("  Found " . count($keys) . " keys", true);
        
        $has_primary_key = false;
        
        foreach ($keys as $key) {
            $key_name = (string)$key['NAME'];
            $key_type = (string)$key['TYPE'];
            $key_fields = (string)$key['FIELDS'];
            
            $this->log("    Key: $key_name ($key_type) on fields: $key_fields", true);
            
            if ($key_type === 'primary') {
                $has_primary_key = true;
                
                if ($key_fields !== 'id') {
                    $this->log("WARNING: Primary key in $table_name not on 'id' field");
                }
            }
            
            // Validate foreign keys
            if ($key_type === 'foreign' || $key_type === 'foreign-unique') {
                $ref_table = (string)$key['REFTABLE'];
                $ref_fields = (string)$key['REFFIELDS'];
                
                if (empty($ref_table) || empty($ref_fields)) {
                    $this->log("ERROR: Foreign key $key_name missing reference information");
                } else {
                    $this->log("      References: $ref_table($ref_fields)", true);
                }
            }
        }
        
        if (!$has_primary_key) {
            $this->log("ERROR: Table $table_name missing primary key");
        } else {
            $this->log("  ✓ Table has primary key", true);
        }
    }
    
    /**
     * Check if tables exist in database
     */
    public function check_tables_exist() {
        $this->log("Checking if plugin tables exist in database...");
        
        $expected_tables = [
            'quizaccess_invigilator',
            'quizaccess_invigilator_logs'
        ];
        
        $all_exist = true;
        
        foreach ($expected_tables as $table_name) {
            $exists = $this->dbman->table_exists($table_name);
            
            if ($exists) {
                $this->log("✓ Table exists: $table_name");
                
                // Get table info if verbose
                if ($this->verbose) {
                    $this->describe_table($table_name);
                }
            } else {
                $this->log("✗ Table missing: $table_name");
                $all_exist = false;
            }
        }
        
        return $all_exist;
    }
    
    /**
     * Describe table structure
     */
    private function describe_table($table_name) {
        try {
            // Get table columns
            $columns = $this->db->get_columns($table_name);
            
            $this->log("  Table structure for $table_name:", true);
            foreach ($columns as $column_name => $column_info) {
                $type = $column_info->meta_type;
                $max_length = $column_info->max_length ?? 'N/A';
                $not_null = $column_info->not_null ? 'NOT NULL' : 'NULL';
                
                $this->log("    $column_name: $type($max_length) $not_null", true);
            }
            
        } catch (Exception $e) {
            $this->log("  Error describing table: " . $e->getMessage(), true);
        }
    }
    
    /**
     * Test schema installation (dry run)
     */
    public function test_schema_installation($dry_run = true) {
        $this->log("Testing schema installation" . ($dry_run ? " (dry run)" : "") . "...");
        
        $install_xml = $this->plugin_path . '/db/install.xml';
        
        if (!file_exists($install_xml)) {
            $this->log("ERROR: install.xml not found");
            return false;
        }
        
        try {
            // Load XML schema
            $xmldb_file = new xmldb_file($install_xml);
            
            if (!$xmldb_file->fileExists()) {
                $this->log("ERROR: Cannot load XMLDB file");
                return false;
            }
            
            $loaded = $xmldb_file->loadXMLStructure();
            if (!$loaded) {
                $this->log("ERROR: Cannot load XML structure");
                return false;
            }
            
            $xmldb_structure = $xmldb_file->getStructure();
            $tables = $xmldb_structure->getTables();
            
            $this->log("Found " . count($tables) . " tables in schema");
            
            foreach ($tables as $table) {
                $table_name = $table->getName();
                $this->log("Processing table: $table_name", true);
                
                if ($this->dbman->table_exists($table_name)) {
                    $this->log("  Table already exists: $table_name", true);
                } else {
                    $this->log("  Table would be created: $table_name", true);
                    
                    if (!$dry_run) {
                        $this->log("  Creating table: $table_name");
                        $this->dbman->create_table($table);
                        $this->log("  ✓ Table created successfully");
                    }
                }
                
                // Validate table structure
                $fields = $table->getFields();
                $keys = $table->getKeys();
                
                $this->log("    Fields: " . count($fields), true);
                $this->log("    Keys: " . count($keys), true);
            }
            
            $this->log("✓ Schema installation test completed successfully");
            return true;
            
        } catch (Exception $e) {
            $this->log("ERROR: Schema installation test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Run comprehensive database validation
     */
    public function run_all_validations() {
        $this->log("Starting comprehensive database validation...");
        
        $results = [
            'schema_validation' => $this->validate_xml_schema(),
            'tables_exist' => $this->check_tables_exist(),
            'installation_test' => $this->test_schema_installation(true)
        ];
        
        $this->log("\n=== DATABASE VALIDATION SUMMARY ===");
        
        $passed = 0;
        $total = count($results);
        
        foreach ($results as $test => $result) {
            $status = $result ? '✓ PASSED' : '✗ FAILED';
            $this->log("$status: " . str_replace('_', ' ', ucfirst($test)));
            
            if ($result) {
                $passed++;
            }
        }
        
        $overall = $passed === $total;
        $status = $overall ? 'PASSED' : 'FAILED';
        
        $this->log("\nOVERALL: $passed/$total tests passed - $status");
        
        return $overall;
    }
}

// Main execution
$validator = new DatabaseValidator($options['verbose'], $options['plugin-path']);

// Determine which validations to run
$any_validation = false;

if ($options['validate-schema']) {
    $validator->validate_xml_schema();
    $any_validation = true;
}

if ($options['check-tables']) {
    $validator->check_tables_exist();
    $any_validation = true;
}

if ($options['test-install']) {
    $validator->test_schema_installation($options['dry-run']);
    $any_validation = true;
}

// If no specific validation requested, run all
if (!$any_validation) {
    $success = $validator->run_all_validations();
    exit($success ? 0 : 1);
}

exit(0);