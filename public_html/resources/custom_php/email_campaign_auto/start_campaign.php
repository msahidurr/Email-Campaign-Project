<?php
// start_campaign.php - Start an email campaign
// Version: 2025-01-25-Fixed-Session-Directory

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';

$version = '2025-01-25-Fixed-Session-Directory';
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

// Ensure campaign data directory exists
if (!ensure_directory(CAMPAIGN_DATA_DIR, 0755)) {
    log_message("Failed to create campaign data directory " . CAMPAIGN_DATA_DIR);
    echo json_encode(['success' => false, 'error' => 'Failed to create campaign data directory']);
    exit;
}

$progress_file = CAMPAIGN_DATA_DIR . "/campaign_{$campaign_id}_progress.json";

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

$command = "php " . SEND_EMAILS_SCRIPT . " '{$campaign_id}' >> " . SEND_EMAILS_LOG . " 2>&1 &";
log_message("Starting send_emails1.php for campaign {$campaign_id} with command: {$command}");
exec($command, $output, $return_var);

if ($return_var !== 0) {
    log_message("Failed to execute send_emails1.php for campaign {$campaign_id}, return: {$return_var}");
    echo json_encode(['success' => false, 'error' => 'Failed to start campaign process']);
    exit;
}

log_message("Successfully started campaign {$campaign_id}");
echo json_encode(['success' => true, 'campaign_id' => $campaign_id]);
?>
