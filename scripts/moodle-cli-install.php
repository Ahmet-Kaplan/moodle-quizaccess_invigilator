<?php
/**
 * Moodle CLI Plugin Installation Script
 * 
 * This script uses Moodle's CLI interface to install and upgrade the invigilator plugin
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
        'install' => false,
        'upgrade' => false,
        'check' => false,
        'verbose' => false,
        'non-interactive' => false
    ),
    array(
        'h' => 'help',
        'i' => 'install',
        'u' => 'upgrade',
        'c' => 'check',
        'v' => 'verbose',
        'n' => 'non-interactive'
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "
Moodle Invigilator Plugin CLI Installation Script

Options:
-h, --help              Print out this help
-i, --install           Install the plugin
-u, --upgrade           Upgrade the plugin
-c, --check             Check plugin status
-v, --verbose           Verbose output
-n, --non-interactive   Non-interactive mode (auto-confirm)

Example:
\$sudo -u www-data /usr/bin/php {$argv[0]} --install --verbose
";
    echo $help;
    exit(0);
}

/**
 * Output message with optional verbose mode
 */
function output_message($message, $verbose = false) {
    global $options;
    if (!$verbose || $options['verbose']) {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    }
}

/**
 * Check if plugin is installed
 */
function check_plugin_installed() {
    global $DB;
    
    try {
        $plugin = $DB->get_record('config_plugins', array('plugin' => 'quizaccess_invigilator', 'name' => 'version'));
        return $plugin ? $plugin->value : false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check plugin status
 */
function check_plugin_status() {
    global $CFG;
    
    output_message("Checking plugin status...");
    
    // Check if plugin files exist
    $plugin_path = $CFG->dirroot . '/mod/quiz/accessrule/invigilator';
    if (!is_dir($plugin_path)) {
        output_message("ERROR: Plugin files not found at: $plugin_path");
        return false;
    }
    
    output_message("✓ Plugin files found at: $plugin_path");
    
    // Check version.php
    $version_file = $plugin_path . '/version.php';
    if (!file_exists($version_file)) {
        output_message("ERROR: version.php not found");
        return false;
    }
    
    // Get plugin version from file
    $plugin_info = new stdClass();
    include($version_file);
    $file_version = isset($plugin->version) ? $plugin->version : 'Unknown';
    
    output_message("✓ Plugin version in file: $file_version");
    
    // Check database version
    $db_version = check_plugin_installed();
    if ($db_version) {
        output_message("✓ Plugin version in database: $db_version");
        
        if ($file_version > $db_version) {
            output_message("! Plugin needs upgrade (file: $file_version, db: $db_version)");
            return 'upgrade_needed';
        } elseif ($file_version == $db_version) {
            output_message("✓ Plugin is up to date");
            return 'up_to_date';
        } else {
            output_message("! Database version is newer than file version");
            return 'version_mismatch';
        }
    } else {
        output_message("! Plugin not installed in database");
        return 'not_installed';
    }
}

/**
 * Install or upgrade plugin
 */
function install_upgrade_plugin($is_upgrade = false) {
    global $CFG, $options;
    
    $action = $is_upgrade ? 'upgrade' : 'install';
    output_message("Starting plugin $action...");
    
    // Check if we're in non-interactive mode
    if (!$options['non-interactive']) {
        echo "This will $action the quizaccess_invigilator plugin. Continue? (y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim($line) !== 'y' && trim($line) !== 'Y') {
            output_message("Operation cancelled by user");
            return false;
        }
    }
    
    try {
        // Purge caches
        output_message("Purging caches...");
        purge_all_caches();
        
        // Get plugin manager
        $pluginman = core_plugin_manager::instance();
        
        // Check for missing plugins
        output_message("Checking for missing plugins...");
        $missing = $pluginman->get_missing_plugins();
        
        if (!empty($missing)) {
            output_message("Found missing plugins, running installation...");
            
            // Install missing plugins
            foreach ($missing as $component => $plugin) {
                if ($component === 'quizaccess_invigilator') {
                    output_message("Installing plugin: $component");
                    
                    // Trigger plugin installation
                    $pluginman->install_plugins(array($component => $plugin), true, true);
                }
            }
        }
        
        // Check for plugin upgrades
        output_message("Checking for plugin upgrades...");
        $upgrades = $pluginman->get_plugins_requiring_upgrade();
        
        if (!empty($upgrades)) {
            foreach ($upgrades as $component => $plugin) {
                if ($component === 'quizaccess_invigilator') {
                    output_message("Upgrading plugin: $component");
                    
                    // Run upgrade
                    upgrade_plugin($component, $plugin->versiondisk, $plugin->versiondb);
                }
            }
        }
        
        // Final cache purge
        output_message("Final cache purge...");
        purge_all_caches();
        
        output_message("✓ Plugin $action completed successfully!");
        return true;
        
    } catch (Exception $e) {
        output_message("ERROR: Plugin $action failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Upgrade a specific plugin
 */
function upgrade_plugin($component, $version_disk, $version_db) {
    global $CFG, $DB;
    
    output_message("Upgrading $component from $version_db to $version_disk", true);
    
    // Include the plugin's upgrade script
    $plugin_dir = core_component::get_plugin_directory('quizaccess', 'invigilator');
    $upgrade_file = $plugin_dir . '/db/upgrade.php';
    
    if (file_exists($upgrade_file)) {
        output_message("Running upgrade script...", true);
        
        // Set up upgrade environment
        $oldversion = $version_db;
        
        // Include and run upgrade
        include_once($upgrade_file);
        
        if (function_exists('xmldb_quizaccess_invigilator_upgrade')) {
            $result = xmldb_quizaccess_invigilator_upgrade($oldversion);
            if (!$result) {
                throw new Exception("Upgrade function returned false");
            }
        }
    }
    
    // Update version in database
    set_config('version', $version_disk, 'quizaccess_invigilator');
    
    output_message("✓ Plugin upgrade completed", true);
}

// Main execution
output_message("Moodle Invigilator Plugin CLI Installation Script");
output_message("Moodle version: " . $CFG->version);
output_message("Moodle release: " . $CFG->release);

// Determine action
if ($options['check']) {
    $status = check_plugin_status();
    exit($status === false ? 1 : 0);
} elseif ($options['install']) {
    $status = check_plugin_status();
    
    if ($status === 'not_installed') {
        $success = install_upgrade_plugin(false);
        exit($success ? 0 : 1);
    } elseif ($status === 'up_to_date') {
        output_message("Plugin is already installed and up to date");
        exit(0);
    } elseif ($status === 'upgrade_needed') {
        output_message("Plugin is installed but needs upgrade. Use --upgrade option.");
        exit(1);
    } else {
        output_message("Cannot install: " . $status);
        exit(1);
    }
} elseif ($options['upgrade']) {
    $status = check_plugin_status();
    
    if ($status === 'upgrade_needed' || $status === 'up_to_date') {
        $success = install_upgrade_plugin(true);
        exit($success ? 0 : 1);
    } elseif ($status === 'not_installed') {
        output_message("Plugin is not installed. Use --install option first.");
        exit(1);
    } else {
        output_message("Cannot upgrade: " . $status);
        exit(1);
    }
} else {
    // Default action: check status
    output_message("No action specified. Checking plugin status...");
    $status = check_plugin_status();
    
    if ($status === 'not_installed') {
        output_message("To install the plugin, run: php {$argv[0]} --install");
    } elseif ($status === 'upgrade_needed') {
        output_message("To upgrade the plugin, run: php {$argv[0]} --upgrade");
    }
    
    exit($status === false ? 1 : 0);
}