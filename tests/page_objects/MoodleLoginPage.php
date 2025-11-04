<?php
/**
 * Page Object Model for Moodle Login Page
 * 
 * @package    quizaccess_invigilator
 * @copyright  2024 Moodle Invigilator Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../browser_automation_setup.php');

/**
 * Page Object Model for Moodle Login functionality
 */
class MoodleLoginPage {
    
    /** @var InvigilatorBrowserTestSetup Browser test setup instance */
    private $browser;
    
    /** @var string WebDriver session ID */
    private $session_id;
    
    // Page selectors
    const USERNAME_FIELD = '#username';
    const PASSWORD_FIELD = '#password';
    const LOGIN_BUTTON = '#loginbtn';
    const ERROR_MESSAGE = '.alert-danger';
    const LOGOUT_LINK = '.usermenu .dropdown-toggle';
    const LOGOUT_CONFIRM = 'a[href*="logout"]';
    
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
     * Navigate to login page
     * 
     * @return bool Success status
     */
    public function navigateToLogin() {
        return $this->browser->navigateToUrl($this->session_id, '/login/index.php');
    }
    
    /**
     * Perform login with credentials
     * 
     * @param string $username Username
     * @param string $password Password
     * @return bool Success status
     */
    public function login($username, $password) {
        // Wait for login form to be present
        $username_field = $this->browser->waitForElement($this->session_id, self::USERNAME_FIELD, 10);
        if (!$username_field) {
            return false;
        }
        
        // Enter username
        if (!$this->browser->sendKeysToElement($this->session_id, $username_field['ELEMENT'], $username)) {
            return false;
        }
        
        // Find password field
        $password_field = $this->browser->findElement($this->session_id, self::PASSWORD_FIELD);
        if (!$password_field) {
            return false;
        }
        
        // Enter password
        if (!$this->browser->sendKeysToElement($this->session_id, $password_field['ELEMENT'], $password)) {
            return false;
        }
        
        // Click login button
        $login_button = $this->browser->findElement($this->session_id, self::LOGIN_BUTTON);
        if (!$login_button) {
            return false;
        }
        
        return $this->browser->clickElement($this->session_id, $login_button['ELEMENT']);
    }
    
    /**
     * Check if login was successful
     * 
     * @return bool True if logged in successfully
     */
    public function isLoginSuccessful() {
        // Check for user menu presence (indicates successful login)
        $user_menu = $this->browser->findElement($this->session_id, self::LOGOUT_LINK);
        return $user_menu !== false;
    }
    
    /**
     * Check if login error is displayed
     * 
     * @return bool True if error message is present
     */
    public function hasLoginError() {
        $error_element = $this->browser->findElement($this->session_id, self::ERROR_MESSAGE);
        return $error_element !== false;
    }
    
    /**
     * Get login error message
     * 
     * @return string|false Error message or false if no error
     */
    public function getLoginErrorMessage() {
        $error_element = $this->browser->findElement($this->session_id, self::ERROR_MESSAGE);
        if (!$error_element) {
            return false;
        }
        
        // Get element text using JavaScript
        $script = 'return arguments[0].textContent;';
        return $this->browser->executeScript($this->session_id, $script, [$error_element]);
    }
    
    /**
     * Perform logout
     * 
     * @return bool Success status
     */
    public function logout() {
        // Click user menu
        $user_menu = $this->browser->findElement($this->session_id, self::LOGOUT_LINK);
        if (!$user_menu) {
            return false;
        }
        
        if (!$this->browser->clickElement($this->session_id, $user_menu['ELEMENT'])) {
            return false;
        }
        
        // Wait for dropdown to appear and click logout
        sleep(1);
        $logout_link = $this->browser->findElement($this->session_id, self::LOGOUT_CONFIRM);
        if (!$logout_link) {
            return false;
        }
        
        return $this->browser->clickElement($this->session_id, $logout_link['ELEMENT']);
    }
    
    /**
     * Check if user is logged out
     * 
     * @return bool True if logged out
     */
    public function isLoggedOut() {
        // Check for login form presence (indicates logged out)
        $username_field = $this->browser->findElement($this->session_id, self::USERNAME_FIELD);
        return $username_field !== false;
    }
}