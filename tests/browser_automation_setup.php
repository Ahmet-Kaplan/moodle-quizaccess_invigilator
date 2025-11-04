<?php
/**
 * Selenium WebDriver Test Setup for Moodle Invigilator Plugin
 * 
 * This file provides the base configuration and utilities for browser automation testing
 * using Selenium WebDriver with Chrome and Firefox browsers.
 * 
 * @package    quizaccess_invigilator
 * @copyright  2024 Moodle Invigilator Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../config.php');

/**
 * Base class for Selenium WebDriver browser automation tests
 */
class InvigilatorBrowserTestSetup {
    
    /** @var string WebDriver server URL */
    private $webdriver_url;
    
    /** @var array Browser capabilities */
    private $capabilities;
    
    /** @var string Base Moodle URL for testing */
    private $moodle_url;
    
    /** @var int Default timeout in seconds */
    private $default_timeout;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->webdriver_url = getenv('WEBDRIVER_URL') ?: 'http://localhost:4444/wd/hub';
        $this->moodle_url = getenv('MOODLE_TEST_URL') ?: 'http://localhost:8081';
        $this->default_timeout = 30;
        $this->capabilities = [];
    }
    
    /**
     * Configure Chrome browser capabilities
     * 
     * @return array Chrome capabilities
     */
    public function getChromeCapabilities() {
        return [
            'browserName' => 'chrome',
            'version' => 'latest',
            'chromeOptions' => [
                'args' => [
                    '--no-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--window-size=1920,1080',
                    '--disable-extensions',
                    '--disable-plugins',
                    '--disable-images',
                    '--disable-javascript-harmony-shipping',
                    '--disable-background-timer-throttling',
                    '--disable-renderer-backgrounding',
                    '--disable-backgrounding-occluded-windows',
                    '--disable-features=TranslateUI',
                    '--disable-ipc-flooding-protection',
                    '--use-fake-ui-for-media-stream', // Allow screen capture without user interaction
                    '--use-fake-device-for-media-stream',
                    '--allow-running-insecure-content',
                    '--disable-web-security',
                    '--disable-features=VizDisplayCompositor'
                ],
                'prefs' => [
                    'profile.default_content_setting_values.media_stream_camera' => 1,
                    'profile.default_content_setting_values.media_stream_mic' => 1,
                    'profile.default_content_setting_values.notifications' => 2
                ]
            ]
        ];
    }
    
    /**
     * Configure Firefox browser capabilities
     * 
     * @return array Firefox capabilities
     */
    public function getFirefoxCapabilities() {
        return [
            'browserName' => 'firefox',
            'version' => 'latest',
            'firefoxOptions' => [
                'args' => [
                    '--headless',
                    '--width=1920',
                    '--height=1080'
                ],
                'prefs' => [
                    'media.navigator.permission.disabled' => true,
                    'media.navigator.streams.fake' => true,
                    'media.getusermedia.screensharing.enabled' => true,
                    'media.getusermedia.screensharing.allowed_domains' => 'localhost',
                    'dom.disable_beforeunload' => true,
                    'browser.tabs.warnOnClose' => false,
                    'browser.sessionstore.resume_from_crash' => false
                ]
            ]
        ];
    }
    
    /**
     * Initialize WebDriver session
     * 
     * @param string $browser Browser type ('chrome' or 'firefox')
     * @return resource|false WebDriver session or false on failure
     */
    public function initializeWebDriver($browser = 'chrome') {
        $capabilities = ($browser === 'firefox') ? 
            $this->getFirefoxCapabilities() : 
            $this->getChromeCapabilities();
        
        $session_data = [
            'desiredCapabilities' => $capabilities
        ];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($session_data),
                'timeout' => $this->default_timeout
            ]
        ]);
        
        $response = file_get_contents($this->webdriver_url . '/session', false, $context);
        
        if ($response === false) {
            return false;
        }
        
        $session_info = json_decode($response, true);
        
        if (!isset($session_info['sessionId'])) {
            return false;
        }
        
        return $session_info['sessionId'];
    }
    
    /**
     * Navigate to URL
     * 
     * @param string $session_id WebDriver session ID
     * @param string $url URL to navigate to
     * @return bool Success status
     */
    public function navigateToUrl($session_id, $url) {
        $full_url = $this->moodle_url . $url;
        
        $data = ['url' => $full_url];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($data),
                'timeout' => $this->default_timeout
            ]
        ]);
        
        $response = file_get_contents(
            $this->webdriver_url . '/session/' . $session_id . '/url',
            false,
            $context
        );
        
        return $response !== false;
    }
    
    /**
     * Find element by selector
     * 
     * @param string $session_id WebDriver session ID
     * @param string $selector CSS selector
     * @return array|false Element data or false if not found
     */
    public function findElement($session_id, $selector) {
        $data = [
            'using' => 'css selector',
            'value' => $selector
        ];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($data),
                'timeout' => $this->default_timeout
            ]
        ]);
        
        $response = file_get_contents(
            $this->webdriver_url . '/session/' . $session_id . '/element',
            false,
            $context
        );
        
        if ($response === false) {
            return false;
        }
        
        $element_data = json_decode($response, true);
        
        return isset($element_data['value']['ELEMENT']) ? $element_data['value'] : false;
    }
    
    /**
     * Click element
     * 
     * @param string $session_id WebDriver session ID
     * @param string $element_id Element ID
     * @return bool Success status
     */
    public function clickElement($session_id, $element_id) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => '{}',
                'timeout' => $this->default_timeout
            ]
        ]);
        
        $response = file_get_contents(
            $this->webdriver_url . '/session/' . $session_id . '/element/' . $element_id . '/click',
            false,
            $context
        );
        
        return $response !== false;
    }
    
    /**
     * Send keys to element
     * 
     * @param string $session_id WebDriver session ID
     * @param string $element_id Element ID
     * @param string $text Text to send
     * @return bool Success status
     */
    public function sendKeysToElement($session_id, $element_id, $text) {
        $data = ['value' => str_split($text)];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($data),
                'timeout' => $this->default_timeout
            ]
        ]);
        
        $response = file_get_contents(
            $this->webdriver_url . '/session/' . $session_id . '/element/' . $element_id . '/value',
            false,
            $context
        );
        
        return $response !== false;
    }
    
    /**
     * Execute JavaScript
     * 
     * @param string $session_id WebDriver session ID
     * @param string $script JavaScript code
     * @param array $args Script arguments
     * @return mixed Script result or false on failure
     */
    public function executeScript($session_id, $script, $args = []) {
        $data = [
            'script' => $script,
            'args' => $args
        ];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($data),
                'timeout' => $this->default_timeout
            ]
        ]);
        
        $response = file_get_contents(
            $this->webdriver_url . '/session/' . $session_id . '/execute/sync',
            false,
            $context
        );
        
        if ($response === false) {
            return false;
        }
        
        $result = json_decode($response, true);
        
        return isset($result['value']) ? $result['value'] : false;
    }
    
    /**
     * Wait for element to be present
     * 
     * @param string $session_id WebDriver session ID
     * @param string $selector CSS selector
     * @param int $timeout Timeout in seconds
     * @return array|false Element data or false if timeout
     */
    public function waitForElement($session_id, $selector, $timeout = null) {
        $timeout = $timeout ?: $this->default_timeout;
        $start_time = time();
        
        while (time() - $start_time < $timeout) {
            $element = $this->findElement($session_id, $selector);
            if ($element !== false) {
                return $element;
            }
            sleep(1);
        }
        
        return false;
    }
    
    /**
     * Take screenshot
     * 
     * @param string $session_id WebDriver session ID
     * @return string|false Base64 encoded screenshot or false on failure
     */
    public function takeScreenshot($session_id) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->default_timeout
            ]
        ]);
        
        $response = file_get_contents(
            $this->webdriver_url . '/session/' . $session_id . '/screenshot',
            false,
            $context
        );
        
        if ($response === false) {
            return false;
        }
        
        $result = json_decode($response, true);
        
        return isset($result['value']) ? $result['value'] : false;
    }
    
    /**
     * Close WebDriver session
     * 
     * @param string $session_id WebDriver session ID
     * @return bool Success status
     */
    public function closeSession($session_id) {
        $context = stream_context_create([
            'http' => [
                'method' => 'DELETE',
                'timeout' => $this->default_timeout
            ]
        ]);
        
        $response = file_get_contents(
            $this->webdriver_url . '/session/' . $session_id,
            false,
            $context
        );
        
        return $response !== false;
    }
    
    /**
     * Get Moodle base URL
     * 
     * @return string Moodle URL
     */
    public function getMoodleUrl() {
        return $this->moodle_url;
    }
    
    /**
     * Set default timeout
     * 
     * @param int $timeout Timeout in seconds
     */
    public function setTimeout($timeout) {
        $this->default_timeout = $timeout;
    }
}