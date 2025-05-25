<?php
// resume_campaign.php - Resume a paused email campaign
// Version: 2025-01-25-Dynamic-Paths

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';

header('Content-Type: application/json');

$version = '2025-01-25-Dynamic-Paths';
log_message("Starting resume_campaign.php (Version: {$version})");

// Session configuration
if (!ensure_directory(SESSION_PATH)) {
    log_message("Failed to create session directory " . SESSION_PATH);
    echo json_encode(['success' => false, 'error' => 'Session directory creation failed']);
    exit;
}

ini_set('session.save_path', SESSION_PATH);
ini_set('session.name', 'PHPSESSID');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

if (!session_start()) {
    $error = error_get_last();
    $error_msg = $error ? $error['message'] : 'Unknown error';
    log_message("Failed to start session: {$error_msg}");
    echo json_encode(['success' => false, 'error' => 'Session initialization failed']);
    exit;
}

$campaign_id = isset($_GET['campaign_id']) ? trim($_GET['campaign_id']) : '';
if (empty($campaign_id) || !preg_match('/^[a-z0-9]{12}$/', $campaign_id)) {
    log_message("Invalid or missing campaign ID: {$campaign_id}");
    echo json_encode(['success' => false, 'error' => 'Invalid campaign ID']);
    exit;
}

log_message("Attempting to resume campaign {$campaign_id}");

$progress_file = CAMPAIGN_DATA_DIR . "/campaign_{$campaign_id}_progress.json";
if (!file_exists($progress_file)) {
    log_message("Progress file {$progress_file} not found for campaign {$campaign_id}");
    echo json_encode(['success' => false, 'error' => 'Campaign not found']);
    exit;
}

$progress_data = @file_get_contents($progress_file);
if ($progress_data === false) {
    log_message("Failed to read progress file {$progress_file} for campaign {$campaign_id}");
    echo json_encode(['success' => false, 'error' => 'Failed to read campaign data']);
    exit;
}

$progress = json_decode($progress_data, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    log_message("Failed to decode JSON from progress file {$progress_file}: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Invalid campaign data']);
    exit;
}

if ($progress['status'] !== 'paused') {
    log_message("Campaign {$campaign_id} is not paused, current status: {$progress['status']}");
    echo json_encode(['success' => false, 'error' => 'Campaign is not paused']);
    exit;
}

if ($progress['completed']) {
    log_message("Campaign {$campaign_id} is already completed");
    echo json_encode(['success' => false, 'error' => 'Campaign is already completed']);
    exit;
}

$progress['status'] = 'pending';
$progress['messages'][] = "Campaign resumed at " . date('Y-m-d H:i:s');
$progress['last_updated'] = time();

$write_success = false;
for ($attempt = 1; $attempt <= 3; $attempt++) {
    $result = @file_put_contents($progress_file, json_encode($progress, JSON_PRETTY_PRINT), LOCK_EX);
    if ($result !== false) {
        @chmod($progress_file, 0600);
        $write_success = true;
        log_message("Successfully wrote resumed status to {$progress_file} on attempt {$attempt}");
        break;
    }
    log_message("Failed to write to progress file {$progress_file} on attempt {$attempt}");
    usleep(100000);
}

if (!$write_success) {
    log_message("Failed to resume campaign {$campaign_id}: Unable to write to {$progress_file} after 3 attempts");
    echo json_encode(['success' => false, 'error' => 'Failed to resume campaign']);
    exit;
}

$command = "php " . SEND_EMAILS_SCRIPT . " '{$campaign_id}' >> " . SEND_EMAILS_LOG . " 2>&1 &";
log_message("Starting send_emails1.php for resumed campaign {$campaign_id} with command: {$command}");
exec($command, $output, $return_var);

if ($return_var !== 0) {
    log_message("Failed to execute send_emails1.php for campaign {$campaign_id}, return: {$return_var}, output: " . implode("\n", $output));
    echo json_encode(['success' => false, 'error' => 'Failed to restart campaign process']);
    exit;
}

log_message("Successfully resumed campaign {$campaign_id}, executed send_emails1.php");
echo json_encode(['success' => true, 'message' => 'Campaign resumed']);
?>
