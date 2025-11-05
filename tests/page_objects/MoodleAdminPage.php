<?php
/**
 * Page Object Model for Moodle Admin Interface
 * 
 * @package    quizaccess_invigilator
 * @copyright  2024 Moodle Invigilator Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../browser_automation_setup.php');

/**
 * Page Object Model for Moodle Admin functionality
 */
class MoodleAdminPage {
    
    /** @var InvigilatorBrowserTestSetup Browser test setup instance */
    private $browser;
    
    /** @var string WebDriver session ID */
    private $session_id;
    
    // Page selectors
    const ADMIN_MENU = '.navbar .dropdown-toggle';
    const SITE_ADMIN_LINK = 'a[href*="admin"]';
    const PLUGINS_MENU = 'a[href*="admin/plugins.php"]';
    const QUIZ_ACCESS_RULES = 'a[href*="admin/settings.php?section=modsettingsquiz"]';
    const INVIGILATOR_SETTINGS = 'a[href*="invigilator"]';
    const INVIGILATOR_REPORTS = 'a[href*="invigilatorsummary"]';
    const PLUGIN_LIST = '.pluginname';
    const SETTINGS_FORM = '#adminsettings';
    const SAVE_CHANGES_BUTTON = 'input[value*="Save"], button[type="submit"]:contains("Save")';
    
    /**
     * Constructor
     * 
     * @param InvigilatorBrowserTestSetup $browser Browser setup instance
     * @param string $session_id WebDriver session ID
     */
    public function __construct($browser, $session_id) {
        $this->browser = $browser;
        $this->session_id = $session_id;
    }
    
    /**
     * Navigate to site administration
     * 
     * @return bool Success status
     */
    public function navigateToSiteAdmin() {
        return $this->browser->navigateToUrl($this->session_id, '/admin/index.php');
    }
    
    /**
     * Navigate to plugins overview
     * 
     * @return bool Success status
     */
    public function navigateToPlugins() {
        return $this->browser->navigateToUrl($this->session_id, '/admin/plugins.php');
    }
    
    /**
     * Navigate to invigilator reports
     * 
     * @return bool Success status
     */
    public function navigateToInvigilatorReports() {
        return $this->browser->navigateToUrl($this->session_id, '/mod/quiz/accessrule/invigilator/invigilatorsummary.php');
    }
    
    /**
     * Check if invigilator plugin is listed
     * 
     * @return bool True if plugin is found in the list
     */
    public function isInvigilatorPluginListed() {
        // Navigate to plugins page first
        if (!$this->navigateToPlugins()) {
            return false;
        }
        
        // Look for invigilator plugin in the list
        $script = '
            var plugins = document.querySelectorAll(".pluginname");
            for (var i = 0; i < plugins.length; i++) {
                if (plugins[i].textContent.toLowerCase().includes("invigilator")) {
                    return true;
                }
            }
            return false;
        ';
        
        return $this->browser->executeScript($this->session_id, $script) === true;
    }
    
    /**
     * Get invigilator plugin status
     * 
     * @return string|false Plugin status or false if not found
     */
    public function getInvigilatorPluginStatus() {
        if (!$this->navigateToPlugins()) {
            return false;
        }
        
        $script = '
            var plugins = document.querySelectorAll("tr");
            for (var i = 0; i < plugins.length; i++) {
                var row = plugins[i];
                if (row.textContent.toLowerCase().includes("invigilator")) {
                    var statusCell = row.querySelector(".status, .pluginstatus");
                    if (statusCell) {
                        return statusCell.textContent.trim();
                    }
                }
            }
            return false;
        ';
        
        return $this->browser->executeScript($this->session_id, $script);
    }
    
    /**
     * Navigate to quiz settings
     * 
     * @return bool Success status
     */
    public function navigateToQuizSettings() {
        return $this->browser->navigateToUrl($this->session_id, '/admin/settings.php?section=modsettingsquiz');
    }
    
    /**
     * Check if invigilator settings are available
     * 
     * @return bool True if invigilator settings are present
     */
    public function hasInvigilatorSettings() {
        if (!$this->navigateToQuizSettings()) {
            return false;
        }
        
        $script = '
            var settings = document.querySelectorAll("label, .form-label");
            for (var i = 0; i < settings.length; i++) {
                if (settings[i].textContent.toLowerCase().includes("invigilator")) {
                    return true;
                }
            }
            return false;
        ';
        
        return $this->browser->executeScript($this->session_id, $script) === true;
    }
    
    /**
     * Access invigilator reports page
     * 
     * @return bool Success status
     */
    public function accessInvigilatorReports() {
        return $this->navigateToInvigilatorReports();
    }
    
    /**
     * Check if reports page loads successfully
     * 
     * @return bool True if reports page is accessible
     */
    public function isReportsPageAccessible() {
        if (!$this->navigateToInvigilatorReports()) {
            return false;
        }
        
        // Check for common report elements
        $script = '
            return document.querySelector("table, .report-table, .no-data-message") !== null;
        ';
        
        return $this->browser->executeScript($this->session_id, $script) === true;
    }
    
    /**
     * Get screenshot records from reports
     * 
     * @return array|false Array of screenshot records or false if none found
     */
    public function getScreenshotRecords() {
        if (!$this->navigateToInvigilatorReports()) {
            return false;
        }
        
        $script = '
            var records = [];
            var rows = document.querySelectorAll("table tr, .report-row");
            
            for (var i = 1; i < rows.length; i++) { // Skip header row
                var row = rows[i];
                var cells = row.querySelectorAll("td, .report-cell");
                
                if (cells.length > 0) {
                    var record = {
                        student: cells[0] ? cells[0].textContent.trim() : "",
                        quiz: cells[1] ? cells[1].textContent.trim() : "",
                        timestamp: cells[2] ? cells[2].textContent.trim() : "",
                        screenshot: cells[3] ? cells[3].textContent.trim() : ""
                    };
                    records.push(record);
                }
            }
            
            return records.length > 0 ? records : false;
        ';
        
        return $this->browser->executeScript($this->session_id, $script);
    }
    
    /**
     * Filter reports by student
     * 
     * @param string $student_name Student name to filter by
     * @return bool Success status
     */
    public function filterReportsByStudent($student_name) {
        $filter_field = $this->browser->findElement($this->session_id, 'input[name*="student"], #student-filter');
        if (!$filter_field) {
            return false;
        }
        
        if (!$this->browser->sendKeysToElement($this->session_id, $filter_field['ELEMENT'], $student_name)) {
            return false;
        }
        
        $filter_button = $this->browser->findElement($this->session_id, 'input[type="submit"], button[type="submit"]');
        if (!$filter_button) {
            return false;
        }
        
        return $this->browser->clickElement($this->session_id, $filter_button['ELEMENT']);
    }
    
    /**
     * Filter reports by quiz
     * 
     * @param string $quiz_name Quiz name to filter by
     * @return bool Success status
     */
    public function filterReportsByQuiz($quiz_name) {
        $filter_field = $this->browser->findElement($this->session_id, 'select[name*="quiz"], #quiz-filter');
        if (!$filter_field) {
            return false;
        }
        
        // Select quiz from dropdown
        $script = '
            var select = arguments[0];
            var options = select.querySelectorAll("option");
            for (var i = 0; i < options.length; i++) {
                if (options[i].textContent.includes("' . $quiz_name . '")) {
                    select.value = options[i].value;
                    select.dispatchEvent(new Event("change"));
                    return true;
                }
            }
            return false;
        ';
        
        return $this->browser->executeScript($this->session_id, $script, [$filter_field]) === true;
    }
    
    /**
     * Check if screenshot images are viewable
     * 
     * @return bool True if screenshot links are functional
     */
    public function areScreenshotsViewable() {
        $screenshot_links = $this->browser->findElement($this->session_id, 'a[href*="screenshot"], .screenshot-link');
        if (!$screenshot_links) {
            return false;
        }
        
        // Click first screenshot link
        if (!$this->browser->clickElement($this->session_id, $screenshot_links['ELEMENT'])) {
            return false;
        }
        
        // Check if image loads (wait for img element or error)
        sleep(2);
        $script = '
            return document.querySelector("img") !== null || 
                   document.querySelector(".image-viewer") !== null ||
                   document.querySelector(".screenshot-display") !== null;
        ';
        
        return $this->browser->executeScript($this->session_id, $script) === true;
    }
    
    /**
     * Get report metadata
     * 
     * @return array|false Report metadata or false if not available
     */
    public function getReportMetadata() {
        $script = '
            var metadata = {};
            
            // Look for total records count
            var totalElement = document.querySelector(".total-records, .record-count");
            if (totalElement) {
                metadata.total_records = totalElement.textContent.trim();
            }
            
            // Look for date range
            var dateElement = document.querySelector(".date-range, .report-period");
            if (dateElement) {
                metadata.date_range = dateElement.textContent.trim();
            }
            
            // Look for last updated
            var updatedElement = document.querySelector(".last-updated, .report-timestamp");
            if (updatedElement) {
                metadata.last_updated = updatedElement.textContent.trim();
            }
            
            return Object.keys(metadata).length > 0 ? metadata : false;
        ';
        
        return $this->browser->executeScript($this->session_id, $script);
    }
    
    /**
     * Check admin permissions for invigilator features
     * 
     * @return bool True if admin has proper permissions
     */
    public function hasInvigilatorAdminPermissions() {
        // Check if we can access the reports page
        if (!$this->navigateToInvigilatorReports()) {
            return false;
        }
        
        // Look for permission denied or access restricted messages
        $script = '
            var body = document.body.textContent.toLowerCase();
            return !body.includes("access denied") && 
                   !body.includes("permission") && 
                   !body.includes("not authorized");
        ';
        
        return $this->browser->executeScript($this->session_id, $script) === true;
    }
}