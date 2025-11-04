<?php
/**
 * Page Object Model for Moodle Quiz Page
 * 
 * @package    quizaccess_invigilator
 * @copyright  2024 Moodle Invigilator Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../browser_automation_setup.php');

/**
 * Page Object Model for Moodle Quiz functionality
 */
class MoodleQuizPage {
    
    /** @var InvigilatorBrowserTestSetup Browser test setup instance */
    private $browser;
    
    /** @var string WebDriver session ID */
    private $session_id;
    
    // Page selectors
    const QUIZ_START_BUTTON = 'input[value*="Attempt quiz"], button[type="submit"]:contains("Attempt quiz")';
    const QUIZ_CONTINUE_BUTTON = 'input[value*="Continue"], button[type="submit"]:contains("Continue")';
    const QUIZ_SUBMIT_BUTTON = 'input[value*="Submit"], button[type="submit"]:contains("Submit")';
    const INVIGILATOR_PERMISSION_DIALOG = '.invigilator-permission-dialog';
    const INVIGILATOR_ALLOW_BUTTON = '.invigilator-allow-btn, #invigilator-allow';
    const INVIGILATOR_DENY_BUTTON = '.invigilator-deny-btn, #invigilator-deny';
    const INVIGILATOR_STATUS = '.invigilator-status';
    const QUIZ_QUESTION = '.que';
    const QUIZ_NAVIGATION = '.qn_buttons';
    const ACCESS_DENIED_MESSAGE = '.accessdenied, .alert-danger';
    const QUIZ_TIMER = '.quiz-timer, #quiz-time-left';
    
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
     * Navigate to quiz page
     * 
     * @param int $quiz_id Quiz ID
     * @return bool Success status
     */
    public function navigateToQuiz($quiz_id) {
        return $this->browser->navigateToUrl($this->session_id, "/mod/quiz/view.php?id={$quiz_id}");
    }
    
    /**
     * Start quiz attempt
     * 
     * @return bool Success status
     */
    public function startQuizAttempt() {
        // Wait for start button
        $start_button = $this->browser->waitForElement($this->session_id, self::QUIZ_START_BUTTON, 10);
        if (!$start_button) {
            return false;
        }
        
        return $this->browser->clickElement($this->session_id, $start_button['ELEMENT']);
    }
    
    /**
     * Continue with quiz (after preflight checks)
     * 
     * @return bool Success status
     */
    public function continueQuiz() {
        $continue_button = $this->browser->waitForElement($this->session_id, self::QUIZ_CONTINUE_BUTTON, 10);
        if (!$continue_button) {
            return false;
        }
        
        return $this->browser->clickElement($this->session_id, $continue_button['ELEMENT']);
    }
    
    /**
     * Check if invigilator permission dialog is displayed
     * 
     * @return bool True if permission dialog is present
     */
    public function hasInvigilatorPermissionDialog() {
        $dialog = $this->browser->findElement($this->session_id, self::INVIGILATOR_PERMISSION_DIALOG);
        return $dialog !== false;
    }
    
    /**
     * Allow invigilator screen capture
     * 
     * @return bool Success status
     */
    public function allowInvigilatorCapture() {
        $allow_button = $this->browser->waitForElement($this->session_id, self::INVIGILATOR_ALLOW_BUTTON, 5);
        if (!$allow_button) {
            return false;
        }
        
        return $this->browser->clickElement($this->session_id, $allow_button['ELEMENT']);
    }
    
    /**
     * Deny invigilator screen capture
     * 
     * @return bool Success status
     */
    public function denyInvigilatorCapture() {
        $deny_button = $this->browser->waitForElement($this->session_id, self::INVIGILATOR_DENY_BUTTON, 5);
        if (!$deny_button) {
            return false;
        }
        
        return $this->browser->clickElement($this->session_id, $deny_button['ELEMENT']);
    }
    
    /**
     * Check if access is denied
     * 
     * @return bool True if access denied message is present
     */
    public function isAccessDenied() {
        $denied_message = $this->browser->findElement($this->session_id, self::ACCESS_DENIED_MESSAGE);
        return $denied_message !== false;
    }
    
    /**
     * Get access denied message
     * 
     * @return string|false Access denied message or false if not present
     */
    public function getAccessDeniedMessage() {
        $denied_element = $this->browser->findElement($this->session_id, self::ACCESS_DENIED_MESSAGE);
        if (!$denied_element) {
            return false;
        }
        
        $script = 'return arguments[0].textContent;';
        return $this->browser->executeScript($this->session_id, $script, [$denied_element]);
    }
    
    /**
     * Check if quiz questions are displayed
     * 
     * @return bool True if quiz questions are present
     */
    public function hasQuizQuestions() {
        $questions = $this->browser->findElement($this->session_id, self::QUIZ_QUESTION);
        return $questions !== false;
    }
    
    /**
     * Check invigilator status
     * 
     * @return string|false Invigilator status or false if not present
     */
    public function getInvigilatorStatus() {
        $status_element = $this->browser->findElement($this->session_id, self::INVIGILATOR_STATUS);
        if (!$status_element) {
            return false;
        }
        
        $script = 'return arguments[0].textContent;';
        return $this->browser->executeScript($this->session_id, $script, [$status_element]);
    }
    
    /**
     * Trigger screenshot capture manually (for testing)
     * 
     * @return bool Success status
     */
    public function triggerScreenshotCapture() {
        $script = '
            if (typeof window.invigilatorCapture !== "undefined") {
                return window.invigilatorCapture.captureScreenshot();
            }
            return false;
        ';
        
        $result = $this->browser->executeScript($this->session_id, $script);
        return $result !== false;
    }
    
    /**
     * Check if screenshot capture is active
     * 
     * @return bool True if capture is active
     */
    public function isScreenshotCaptureActive() {
        $script = '
            if (typeof window.invigilatorCapture !== "undefined") {
                return window.invigilatorCapture.isActive();
            }
            return false;
        ';
        
        $result = $this->browser->executeScript($this->session_id, $script);
        return $result === true;
    }
    
    /**
     * Get screenshot capture interval
     * 
     * @return int|false Capture interval in seconds or false if not available
     */
    public function getScreenshotCaptureInterval() {
        $script = '
            if (typeof window.invigilatorCapture !== "undefined") {
                return window.invigilatorCapture.getInterval();
            }
            return false;
        ';
        
        return $this->browser->executeScript($this->session_id, $script);
    }
    
    /**
     * Wait for screenshot capture to start
     * 
     * @param int $timeout Timeout in seconds
     * @return bool True if capture started within timeout
     */
    public function waitForScreenshotCaptureStart($timeout = 10) {
        $start_time = time();
        
        while (time() - $start_time < $timeout) {
            if ($this->isScreenshotCaptureActive()) {
                return true;
            }
            sleep(1);
        }
        
        return false;
    }
    
    /**
     * Submit quiz
     * 
     * @return bool Success status
     */
    public function submitQuiz() {
        $submit_button = $this->browser->findElement($this->session_id, self::QUIZ_SUBMIT_BUTTON);
        if (!$submit_button) {
            return false;
        }
        
        return $this->browser->clickElement($this->session_id, $submit_button['ELEMENT']);
    }
    
    /**
     * Check if quiz timer is present
     * 
     * @return bool True if timer is displayed
     */
    public function hasQuizTimer() {
        $timer = $this->browser->findElement($this->session_id, self::QUIZ_TIMER);
        return $timer !== false;
    }
    
    /**
     * Get quiz navigation buttons
     * 
     * @return array|false Navigation button elements or false if not present
     */
    public function getQuizNavigation() {
        $navigation = $this->browser->findElement($this->session_id, self::QUIZ_NAVIGATION);
        return $navigation;
    }
    
    /**
     * Simulate browser permission grant for screen capture
     * 
     * @return bool Success status
     */
    public function grantScreenCapturePermission() {
        $script = '
            // Mock the getDisplayMedia API to simulate permission grant
            if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
                navigator.mediaDevices.getDisplayMedia = function() {
                    return Promise.resolve({
                        getVideoTracks: function() {
                            return [{
                                stop: function() {},
                                addEventListener: function() {}
                            }];
                        },
                        getTracks: function() {
                            return [{
                                stop: function() {},
                                addEventListener: function() {}
                            }];
                        }
                    });
                };
                return true;
            }
            return false;
        ';
        
        return $this->browser->executeScript($this->session_id, $script) === true;
    }
    
    /**
     * Simulate browser permission denial for screen capture
     * 
     * @return bool Success status
     */
    public function denyScreenCapturePermission() {
        $script = '
            // Mock the getDisplayMedia API to simulate permission denial
            if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
                navigator.mediaDevices.getDisplayMedia = function() {
                    return Promise.reject(new Error("Permission denied"));
                };
                return true;
            }
            return false;
        ';
        
        return $this->browser->executeScript($this->session_id, $script) === true;
    }
}