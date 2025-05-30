<?php
// start_campaign.php - Start an email campaign with enhanced debugging
// Version: 2025-01-25-Enhanced-Debugging

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';

$version = '2025-01-25-Enhanced-Debugging';
log_message("Starting start_campaign.php (Version: {$version})");

header('Content-Type: application/json');

// Get the actual session path (with fallback support)
$session_path = get_session_path();

// Session configuration with better error handling
if (!ensure_directory($session_path, 0700)) {
    // Use system temp directory as last resort
    $session_path = sys_get_temp_dir();
    log_message("Using system temp directory for sessions: $session_path");
}

ini_set('session.save_path', $session_path);
ini_set('session.name', 'PHPSESSID');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

if (!session_start()) {
    // Try with default session configuration
    ini_restore('session.save_path');
    if (!session_start()) {
        $error = error_get_last();
        $error_msg = $error ? $error['message'] : 'Unknown error';
        log_message("Failed to start session: {$error_msg}");
        echo json_encode(['success' => false, 'error' => 'Session initialization failed']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method: {$_SERVER['REQUEST_METHOD']}");
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';
$session_csrf_token = isset($_SESSION['model_b_csrf_token']) ? $_SESSION['model_b_csrf_token'] : '';

if (empty($csrf_token) || $csrf_token !== $session_csrf_token) {
    log_message("CSRF token validation failed");
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$interval = isset($_POST['interval']) ? (int)$_POST['interval'] : 0;
$recipients = isset($_POST['recipients']) ? trim($_POST['recipients']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$sender_email = isset($_POST['sender_email']) ? trim($_POST['sender_email']) : '';
$max_emails = isset($_POST['max_emails']) ? (int)$_POST['max_emails'] : 0;

log_message("Form data received - Sender: {$sender_email}, Recipients count: " . substr_count($recipients, "\n") + 1);

// Validate sender email exists in config
if (!isset($emailConfigs[$sender_email])) {
    log_message("Invalid sender email: {$sender_email}");
    echo json_encode(['success' => false, 'error' => 'Invalid sender email configuration']);
    exit;
}

// Get sender name from config
$sender_name = $emailConfigs[$sender_email]['sender_name'];

if ($interval < 1 || empty($recipients) || empty($subject) || empty($content) || empty($sender_email) || $max_emails < 1) {
    log_message("Missing or invalid form data");
    echo json_encode(['success' => false, 'error' => 'Missing or invalid form data']);
    exit;
}

$recipient_lines = array_filter(array_map('trim', explode("\n", $recipients)));
$parsed_recipients = [];
foreach ($recipient_lines as $line) {
    $parts = array_map('trim', explode(',', $line));
    if (count($parts) >= 2 && filter_var($parts[0], FILTER_VALIDATE_EMAIL)) {
        $parsed_recipients[] = [$parts[0], $parts[1]];
    }
}

if (empty($parsed_recipients)) {
    log_message("No valid recipients provided");
    echo json_encode(['success' => false, 'error' => 'No valid recipients']);
    exit;
}

// Limit recipients to max_emails
if (count($parsed_recipients) > $max_emails) {
    $parsed_recipients = array_slice($parsed_recipients, 0, $max_emails);
    log_message("Limited recipients to {$max_emails} emails");
}

$campaign_id = bin2hex(random_bytes(6));
log_message("Generated campaign ID: {$campaign_id}");

// Ensure campaign data directory exists
if (!ensure_directory(CAMPAIGN_DATA_DIR, 0755)) {
    log_message("Failed to create campaign data directory " . CAMPAIGN_DATA_DIR);
    echo json_encode(['success' => false, 'error' => 'Failed to create campaign data directory']);
    exit;
}

$progress_file = CAMPAIGN_DATA_DIR . "/campaign_{$campaign_id}_progress.json";
log_message("Progress file path: {$progress_file}");

$progress = [
    'total_emails' => count($parsed_recipients),
    'emails_sent' => 0,
    'messages' => ['Campaign created at ' . date('Y-m-d H:i:s')],
    'status' => 'pending',
    'last_updated' => time(),
    'completed' => false,
    'recipients' => $parsed_recipients,
    'subject' => $subject,
    'content' => $content,
    'sender_email' => $sender_email,
    'sender_name' => $sender_name,
    'interval' => $interval,
    'max_emails' => $max_emails
];

$write_success = false;
for ($attempt = 1; $attempt <= 3; $attempt++) {
    $result = @file_put_contents($progress_file, json_encode($progress, JSON_PRETTY_PRINT), LOCK_EX);
    if ($result !== false) {
        @chmod($progress_file, 0600);
        $write_success = true;
        log_message("Successfully wrote to progress file {$progress_file} on attempt {$attempt}");
        break;
    }
    log_message("Failed to write to progress file {$progress_file} on attempt {$attempt}");
    usleep(100000);
}

if (!$write_success) {
    log_message("Failed to write to progress file {$progress_file} after 3 attempts");
    echo json_encode(['success' => false, 'error' => 'Failed to create campaign']);
    exit;
}

// Check if send_emails1.php exists and is readable
if (!file_exists(SEND_EMAILS_SCRIPT)) {
    log_message("Send emails script not found: " . SEND_EMAILS_SCRIPT);
    echo json_encode(['success' => false, 'error' => 'Send emails script not found']);
    exit;
}

if (!is_readable(SEND_EMAILS_SCRIPT)) {
    log_message("Send emails script not readable: " . SEND_EMAILS_SCRIPT);
    echo json_encode(['success' => false, 'error' => 'Send emails script not readable']);
    exit;
}

// Test PHP executable
$php_executable = 'php';
$php_test = shell_exec("which php 2>/dev/null");
if (empty($php_test)) {
    // Try alternative PHP paths
    $php_paths = ['/usr/bin/php', '/usr/local/bin/php', '/opt/php/bin/php'];
    foreach ($php_paths as $path) {
        if (file_exists($path) && is_executable($path)) {
            $php_executable = $path;
            break;
        }
    }
}

log_message("Using PHP executable: {$php_executable}");

// Create a more robust command with better error handling
$log_file = SEND_EMAILS_LOG;
$error_log = LOG_PATH . '/send_emails_error.log';

// Ensure log files exist and are writable
if (!file_exists($log_file)) {
    @touch($log_file);
    @chmod($log_file, 0644);
}
if (!file_exists($error_log)) {
    @touch($error_log);
    @chmod($error_log, 0644);
}

// Use nohup for better background process handling
$command = "nohup {$php_executable} " . escapeshellarg(SEND_EMAILS_SCRIPT) . " " . escapeshellarg($campaign_id) . " >> " . escapeshellarg($log_file) . " 2>> " . escapeshellarg($error_log) . " &";

log_message("Executing command: {$command}");

// Execute the command and capture output
$output = [];
$return_var = 0;
exec($command, $output, $return_var);

log_message("Command executed with return code: {$return_var}");
if (!empty($output)) {
    log_message("Command output: " . implode("\n", $output));
}

// Give the process a moment to start
sleep(1);

// Check if the process actually started by looking for updates in the progress file
$check_attempts = 0;
$process_started = false;

while ($check_attempts < 5) {
    sleep(1);
    $check_attempts++;
    
    if (file_exists($progress_file)) {
        $current_progress = @file_get_contents($progress_file);
        if ($current_progress !== false) {
            $progress_data = json_decode($current_progress, true);
            if ($progress_data && isset($progress_data['status']) && $progress_data['status'] === 'running') {
                $process_started = true;
                log_message("Background process confirmed started for campaign {$campaign_id}");
                break;
            }
        }
    }
    
    log_message("Waiting for background process to start... attempt {$check_attempts}");
}

if (!$process_started) {
    // Try to start the process directly without background execution for debugging
    log_message("Background process failed to start, trying direct execution for debugging");
    
    $direct_command = "{$php_executable} " . escapeshellarg(SEND_EMAILS_SCRIPT) . " " . escapeshellarg($campaign_id);
    log_message("Direct command: {$direct_command}");
    
    $direct_output = [];
    $direct_return = 0;
    exec($direct_command . " 2>&1", $direct_output, $direct_return);
    
    log_message("Direct execution return code: {$direct_return}");
    log_message("Direct execution output: " . implode("\n", $direct_output));
    
    if ($direct_return !== 0) {
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to start email sending process',
            'debug' => [
                'return_code' => $direct_return,
                'output' => $direct_output,
                'command' => $direct_command
            ]
        ]);
        exit;
    }
}

log_message("Successfully started campaign {$campaign_id}");
echo json_encode(['success' => true, 'campaign_id' => $campaign_id]);
?>
