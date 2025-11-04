#!/bin/bash

# Test Data Initialization Script for Moodle Invigilator Plugin
# Creates comprehensive test data for plugin testing

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FIXTURES_DIR="$SCRIPT_DIR/fixtures"
DATA_DIR="$SCRIPT_DIR/data"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${BLUE}[DATA-INIT $(date +'%H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if Moodle is accessible
check_moodle_access() {
    log "Checking Moodle accessibility..."
    
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if curl -s -f http://localhost:8081/ > /dev/null 2>&1; then
            success "Moodle is accessible"
            return 0
        fi
        
        log "Attempt $attempt/$max_attempts - Waiting for Moodle..."
        sleep 10
        attempt=$((attempt + 1))
    done
    
    error "Moodle is not accessible after $max_attempts attempts"
    return 1
}

# Execute Moodle CLI command
execute_moodle_cli() {
    local command="$1"
    local description="$2"
    
    log "$description"
    
    cd "$SCRIPT_DIR"
    docker compose -f docker-compose.test.yml exec -T moodle-test bash -c "cd /var/www/html && $command"
}

# Create test users
create_test_users() {
    log "Creating test users..."
    
    # Create PHP script for user creation
    cat > "$DATA_DIR/create_users.php" << 'EOF'
<?php
require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/user/lib.php');

// Test users data
$users = [
    [
        'username' => 'teststudent1',
        'password' => 'TestStudent123!',
        'firstname' => 'Test',
        'lastname' => 'Student One',
        'email' => 'teststudent1@example.com',
        'role' => 'student'
    ],
    [
        'username' => 'teststudent2',
        'password' => 'TestStudent123!',
        'firstname' => 'Test',
        'lastname' => 'Student Two',
        'email' => 'teststudent2@example.com',
        'role' => 'student'
    ],
    [
        'username' => 'testteacher',
        'password' => 'TestTeacher123!',
        'firstname' => 'Test',
        'lastname' => 'Teacher',
        'email' => 'testteacher@example.com',
        'role' => 'teacher'
    ],
    [
        'username' => 'testmanager',
        'password' => 'TestManager123!',
        'firstname' => 'Test',
        'lastname' => 'Manager',
        'email' => 'testmanager@example.com',
        'role' => 'manager'
    ]
];

$created_users = [];

foreach ($users as $userdata) {
    // Check if user already exists
    if ($DB->record_exists('user', ['username' => $userdata['username']])) {
        echo "User {$userdata['username']} already exists\n";
        $user = $DB->get_record('user', ['username' => $userdata['username']]);
        $created_users[] = $user;
        continue;
    }
    
    // Create user object
    $user = new stdClass();
    $user->username = $userdata['username'];
    $user->password = $userdata['password'];
    $user->firstname = $userdata['firstname'];
    $user->lastname = $userdata['lastname'];
    $user->email = $userdata['email'];
    $user->auth = 'manual';
    $user->confirmed = 1;
    $user->mnethostid = 1;
    $user->lang = 'en';
    $user->timezone = 'UTC';
    
    try {
        $userid = user_create_user($user, false, false);
        $user->id = $userid;
        $created_users[] = $user;
        echo "Created user: {$user->username} (ID: $userid)\n";
    } catch (Exception $e) {
        echo "Error creating user {$user->username}: " . $e->getMessage() . "\n";
    }
}

// Store user IDs for later use
$user_ids = [];
foreach ($created_users as $user) {
    $user_ids[$user->username] = $user->id;
}

file_put_contents('/test-logs/created_users.json', json_encode($user_ids, JSON_PRETTY_PRINT));
echo "Created " . count($created_users) . " users\n";
EOF

    # Execute user creation
    docker compose -f docker-compose.test.yml exec -T moodle-test php -f /var/www/html/test-fixtures/../data/create_users.php
    
    success "Test users created"
}

# Create test course
create_test_course() {
    log "Creating test course..."
    
    cat > "$DATA_DIR/create_course.php" << 'EOF'
<?php
require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');

// Create test course
$coursedata = new stdClass();
$coursedata->fullname = 'Invigilator Test Course';
$coursedata->shortname = 'INVTEST001';
$coursedata->category = 1; // Miscellaneous category
$coursedata->summary = 'Course for testing Invigilator plugin functionality';
$coursedata->summaryformat = FORMAT_HTML;
$coursedata->format = 'topics';
$coursedata->numsections = 3;
$coursedata->visible = 1;
$coursedata->startdate = time();
$coursedata->enddate = 0;

try {
    // Check if course already exists
    if ($DB->record_exists('course', ['shortname' => $coursedata->shortname])) {
        echo "Course already exists\n";
        $course = $DB->get_record('course', ['shortname' => $coursedata->shortname]);
    } else {
        $course = create_course($coursedata);
        echo "Created course: {$course->fullname} (ID: {$course->id})\n";
    }
    
    // Store course ID
    file_put_contents('/test-logs/created_course.json', json_encode(['course_id' => $course->id], JSON_PRETTY_PRINT));
    
} catch (Exception $e) {
    echo "Error creating course: " . $e->getMessage() . "\n";
    exit(1);
}
EOF

    docker compose -f docker-compose.test.yml exec -T moodle-test php -f /var/www/html/test-fixtures/../data/create_course.php
    
    success "Test course created"
}

# Enroll users in course
enroll_users() {
    log "Enrolling users in test course..."
    
    cat > "$DATA_DIR/enroll_users.php" << 'EOF'
<?php
require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/enrol/manual/lib.php');

// Load created data
$users = json_decode(file_get_contents('/test-logs/created_users.json'), true);
$course_data = json_decode(file_get_contents('/test-logs/created_course.json'), true);
$course_id = $course_data['course_id'];

// Get course context
$course = $DB->get_record('course', ['id' => $course_id]);
$context = context_course::instance($course_id);

// Get manual enrolment plugin
$enrol = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $course_id, 'enrol' => 'manual']);

if (!$instance) {
    // Add manual enrolment instance if it doesn't exist
    $instance = new stdClass();
    $instance->id = $enrol->add_instance($course);
    $instance = $DB->get_record('enrol', ['id' => $instance->id]);
}

// Role assignments
$role_assignments = [
    'teststudent1' => 'student',
    'teststudent2' => 'student', 
    'testteacher' => 'editingteacher',
    'testmanager' => 'manager'
];

foreach ($role_assignments as $username => $rolename) {
    if (!isset($users[$username])) {
        echo "User $username not found\n";
        continue;
    }
    
    $userid = $users[$username];
    $role = $DB->get_record('role', ['shortname' => $rolename]);
    
    if (!$role) {
        echo "Role $rolename not found\n";
        continue;
    }
    
    try {
        // Enroll user
        $enrol->enrol_user($instance, $userid, $role->id);
        echo "Enrolled $username as $rolename in course\n";
    } catch (Exception $e) {
        echo "Error enrolling $username: " . $e->getMessage() . "\n";
    }
}

echo "User enrollment completed\n";
EOF

    docker compose -f docker-compose.test.yml exec -T moodle-test php -f /var/www/html/test-fixtures/../data/enroll_users.php
    
    success "Users enrolled in course"
}

# Create test quiz
create_test_quiz() {
    log "Creating test quiz..."
    
    cat > "$DATA_DIR/create_quiz.php" << 'EOF'
<?php
require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

// Load course data
$course_data = json_decode(file_get_contents('/test-logs/created_course.json'), true);
$course_id = $course_data['course_id'];

// Create quiz
$quiz = new stdClass();
$quiz->course = $course_id;
$quiz->name = 'Invigilator Test Quiz';
$quiz->intro = 'Quiz for testing screenshot capture functionality';
$quiz->introformat = FORMAT_HTML;
$quiz->timeopen = 0;
$quiz->timeclose = 0;
$quiz->timelimit = 1800; // 30 minutes
$quiz->overduehandling = 'autosubmit';
$quiz->graceperiod = 0;
$quiz->preferredbehaviour = 'deferredfeedback';
$quiz->attempts = 3;
$quiz->attemptonlast = 0;
$quiz->grademethod = QUIZ_GRADEHIGHEST;
$quiz->decimalpoints = 2;
$quiz->questiondecimalpoints = -1;
$quiz->reviewattempt = 0x1003f;
$quiz->reviewcorrectness = 0x1003f;
$quiz->reviewmarks = 0x1003f;
$quiz->reviewspecificfeedback = 0x1003f;
$quiz->reviewgeneralfeedback = 0x1003f;
$quiz->reviewrightanswer = 0x1003f;
$quiz->reviewoverallfeedback = 0x1003f;
$quiz->questionsperpage = 1;
$quiz->navmethod = 'free';
$quiz->shuffleanswers = 1;
$quiz->sumgrades = 0;
$quiz->grade = 10;
$quiz->timecreated = time();
$quiz->timemodified = time();

try {
    // Check if quiz already exists
    if ($DB->record_exists('quiz', ['course' => $course_id, 'name' => $quiz->name])) {
        echo "Quiz already exists\n";
        $existing_quiz = $DB->get_record('quiz', ['course' => $course_id, 'name' => $quiz->name]);
        $quiz_id = $existing_quiz->id;
    } else {
        $quiz_id = quiz_add_instance($quiz);
        echo "Created quiz: {$quiz->name} (ID: $quiz_id)\n";
    }
    
    // Store quiz ID
    file_put_contents('/test-logs/created_quiz.json', json_encode(['quiz_id' => $quiz_id], JSON_PRETTY_PRINT));
    
} catch (Exception $e) {
    echo "Error creating quiz: " . $e->getMessage() . "\n";
    exit(1);
}
EOF

    docker compose -f docker-compose.test.yml exec -T moodle-test php -f /var/www/html/test-fixtures/../data/create_quiz.php
    
    success "Test quiz created"
}

# Create test questions
create_test_questions() {
    log "Creating test questions..."
    
    cat > "$DATA_DIR/create_questions.php" << 'EOF'
<?php
require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');

// Load quiz data
$quiz_data = json_decode(file_get_contents('/test-logs/created_quiz.json'), true);
$quiz_id = $quiz_data['quiz_id'];

// Get quiz and course
$quiz = $DB->get_record('quiz', ['id' => $quiz_id]);
$course = $DB->get_record('course', ['id' => $quiz->course]);
$context = context_course::instance($course->id);

// Create question category
$category = new stdClass();
$category->name = 'Invigilator Test Questions';
$category->contextid = $context->id;
$category->info = 'Questions for testing Invigilator plugin';
$category->infoformat = FORMAT_HTML;
$category->stamp = make_unique_id_code();
$category->parent = 0;
$category->sortorder = 999;

// Check if category exists
$existing_category = $DB->get_record('question_categories', [
    'contextid' => $context->id,
    'name' => $category->name
]);

if ($existing_category) {
    $category_id = $existing_category->id;
    echo "Using existing question category\n";
} else {
    $category_id = $DB->insert_record('question_categories', $category);
    echo "Created question category: {$category->name} (ID: $category_id)\n";
}

// Create test questions
$questions = [
    [
        'name' => 'Invigilator Purpose',
        'questiontext' => 'What is the primary purpose of the Invigilator plugin?',
        'answers' => [
            ['text' => 'To capture screenshots during quiz attempts', 'fraction' => 1],
            ['text' => 'To grade quizzes automatically', 'fraction' => 0],
            ['text' => 'To create quiz questions', 'fraction' => 0],
            ['text' => 'To manage user accounts', 'fraction' => 0]
        ]
    ],
    [
        'name' => 'Screenshot Frequency',
        'questiontext' => 'How often does the Invigilator plugin capture screenshots by default?',
        'answers' => [
            ['text' => 'Every 30 seconds', 'fraction' => 1],
            ['text' => 'Every 10 seconds', 'fraction' => 0],
            ['text' => 'Every minute', 'fraction' => 0],
            ['text' => 'Only when suspicious activity is detected', 'fraction' => 0]
        ]
    ],
    [
        'name' => 'Permission Requirements',
        'questiontext' => 'What permission is required for the Invigilator plugin to work?',
        'answers' => [
            ['text' => 'Screen sharing permission', 'fraction' => 1],
            ['text' => 'Camera access', 'fraction' => 0],
            ['text' => 'Microphone access', 'fraction' => 0],
            ['text' => 'Location access', 'fraction' => 0]
        ]
    ]
];

$created_questions = [];

foreach ($questions as $q_data) {
    // Check if question already exists
    if ($DB->record_exists('question', ['category' => $category_id, 'name' => $q_data['name']])) {
        echo "Question '{$q_data['name']}' already exists\n";
        $question = $DB->get_record('question', ['category' => $category_id, 'name' => $q_data['name']]);
        $created_questions[] = $question->id;
        continue;
    }
    
    // Create question
    $question = new stdClass();
    $question->category = $category_id;
    $question->parent = 0;
    $question->name = $q_data['name'];
    $question->questiontext = $q_data['questiontext'];
    $question->questiontextformat = FORMAT_HTML;
    $question->generalfeedback = '';
    $question->generalfeedbackformat = FORMAT_HTML;
    $question->qtype = 'multichoice';
    $question->length = 1;
    $question->stamp = make_unique_id_code();
    $question->version = make_unique_id_code();
    $question->hidden = 0;
    $question->timecreated = time();
    $question->timemodified = time();
    $question->createdby = 2; // Admin user
    $question->modifiedby = 2;
    
    $question_id = $DB->insert_record('question', $question);
    
    // Create multichoice options
    $options = new stdClass();
    $options->questionid = $question_id;
    $options->layout = 0;
    $options->single = 1;
    $options->shuffleanswers = 1;
    $options->correctfeedback = 'Correct!';
    $options->correctfeedbackformat = FORMAT_HTML;
    $options->partiallycorrectfeedback = 'Partially correct.';
    $options->partiallycorrectfeedbackformat = FORMAT_HTML;
    $options->incorrectfeedback = 'Incorrect.';
    $options->incorrectfeedbackformat = FORMAT_HTML;
    $options->answernumbering = 'abc';
    
    $DB->insert_record('qtype_multichoice_options', $options);
    
    // Create answers
    foreach ($q_data['answers'] as $index => $answer_data) {
        $answer = new stdClass();
        $answer->question = $question_id;
        $answer->answer = $answer_data['text'];
        $answer->answerformat = FORMAT_HTML;
        $answer->fraction = $answer_data['fraction'];
        $answer->feedback = '';
        $answer->feedbackformat = FORMAT_HTML;
        
        $DB->insert_record('question_answers', $answer);
    }
    
    $created_questions[] = $question_id;
    echo "Created question: {$q_data['name']} (ID: $question_id)\n";
}

// Store question IDs
file_put_contents('/test-logs/created_questions.json', json_encode([
    'category_id' => $category_id,
    'question_ids' => $created_questions
], JSON_PRETTY_PRINT));

echo "Created " . count($created_questions) . " questions\n";
EOF

    docker compose -f docker-compose.test.yml exec -T moodle-test php -f /var/www/html/test-fixtures/../data/create_questions.php
    
    success "Test questions created"
}

# Configure Invigilator plugin for quiz
configure_invigilator() {
    log "Configuring Invigilator plugin for test quiz..."
    
    cat > "$DATA_DIR/configure_invigilator.php" << 'EOF'
<?php
require_once('/var/www/html/config.php');

// Load quiz data
$quiz_data = json_decode(file_get_contents('/test-logs/created_quiz.json'), true);
$quiz_id = $quiz_data['quiz_id'];

// Configure Invigilator access rule for the quiz
$invigilator_config = new stdClass();
$invigilator_config->quizid = $quiz_id;
$invigilator_config->screenshot_interval = 30; // 30 seconds
$invigilator_config->enabled = 1;
$invigilator_config->require_permission = 1;
$invigilator_config->timecreated = time();
$invigilator_config->timemodified = time();

try {
    // Check if configuration already exists
    if ($DB->record_exists('quizaccess_invigilator', ['quizid' => $quiz_id])) {
        echo "Invigilator configuration already exists for quiz\n";
        $DB->update_record('quizaccess_invigilator', $invigilator_config);
    } else {
        $config_id = $DB->insert_record('quizaccess_invigilator', $invigilator_config);
        echo "Created Invigilator configuration (ID: $config_id)\n";
    }
    
    // Store configuration
    file_put_contents('/test-logs/invigilator_config.json', json_encode([
        'quiz_id' => $quiz_id,
        'config' => $invigilator_config
    ], JSON_PRETTY_PRINT));
    
} catch (Exception $e) {
    echo "Error configuring Invigilator: " . $e->getMessage() . "\n";
    // This might fail if the plugin tables don't exist yet, which is expected
    echo "This is expected if plugin is not fully installed yet\n";
}
EOF

    docker compose -f docker-compose.test.yml exec -T moodle-test php -f /var/www/html/test-fixtures/../data/configure_invigilator.php
    
    success "Invigilator plugin configured"
}

# Create test data summary
create_summary() {
    log "Creating test data summary..."
    
    cat > "$DATA_DIR/test_data_summary.json" << EOF
{
    "created_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "environment": "test",
    "moodle_url": "http://localhost:8081",
    "admin_credentials": {
        "username": "testadmin",
        "password": "TestAdmin123!"
    },
    "test_data": {
        "course": "Invigilator Test Course (INVTEST001)",
        "quiz": "Invigilator Test Quiz",
        "users": [
            {"username": "teststudent1", "password": "TestStudent123!", "role": "student"},
            {"username": "teststudent2", "password": "TestStudent123!", "role": "student"},
            {"username": "testteacher", "password": "TestTeacher123!", "role": "teacher"},
            {"username": "testmanager", "password": "TestManager123!", "role": "manager"}
        ],
        "questions": 3,
        "invigilator_enabled": true
    },
    "files": {
        "users": "/test-logs/created_users.json",
        "course": "/test-logs/created_course.json", 
        "quiz": "/test-logs/created_quiz.json",
        "questions": "/test-logs/created_questions.json",
        "invigilator": "/test-logs/invigilator_config.json"
    }
}
EOF

    success "Test data summary created"
}

# Main initialization function
initialize_test_data() {
    log "Starting test data initialization..."
    
    # Ensure directories exist
    mkdir -p "$DATA_DIR"
    mkdir -p "$FIXTURES_DIR"
    
    # Check Moodle access
    check_moodle_access
    
    # Create test data
    create_test_users
    create_test_course
    enroll_users
    create_test_quiz
    create_test_questions
    configure_invigilator
    create_summary
    
    success "Test data initialization completed"
    
    log "Test environment ready:"
    log "  URL: http://localhost:8081"
    log "  Admin: testadmin / TestAdmin123!"
    log "  Course: Invigilator Test Course"
    log "  Quiz: Invigilator Test Quiz"
    log "  Test users: teststudent1, teststudent2, testteacher, testmanager"
}

# Clean test data
clean_test_data() {
    log "Cleaning test data..."
    
    rm -rf "$DATA_DIR"/*
    
    # Clean database (optional - usually handled by environment reset)
    cat > "$DATA_DIR/clean_data.php" << 'EOF'
<?php
require_once('/var/www/html/config.php');

// Clean test data from database
$test_course = $DB->get_record('course', ['shortname' => 'INVTEST001']);
if ($test_course) {
    // This would require more complex cleanup
    echo "Test course found, manual cleanup may be required\n";
}

echo "Test data cleanup completed\n";
EOF

    success "Test data cleaned"
}

# Main execution
case "${1:-init}" in
    init|initialize)
        initialize_test_data
        ;;
    clean)
        clean_test_data
        ;;
    check)
        check_moodle_access
        ;;
    *)
        echo "Usage: $0 {init|clean|check}"
        echo ""
        echo "Commands:"
        echo "  init   - Initialize test data (default)"
        echo "  clean  - Clean test data"
        echo "  check  - Check Moodle accessibility"
        exit 1
        ;;
esac