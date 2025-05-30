<?php
session_start();

$log_file = '/home/644481.cloudwaysapps.com/cbjxkvbavw/private_html/custom_php/email_campaign.log';

function log_message($message) {
    file_put_contents($log_file, "[Model_B] $message at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}

header('Content-Type: application/json');

log_message('Control: Request received');
log_message('Control: Session ID: ' . session_id());

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    log_message('Control: Invalid request');
    exit;
}

log_message('Control: Checking CSRF token');
if (!isset($_POST['csrf_token']) || !isset($_SESSION['model_b_csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token missing']);
    log_message('Control: CSRF token missing');
    exit;
}

if ($_POST['csrf_token'] !== $_SESSION['model_b_csrf_token']) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    log_message('Control: Invalid CSRF token');
    exit;
}

log_message('Control: CSRF token valid');
if (!isset($_POST['action']) || !in_array($_POST['action'], ['pause', 'resume', 'stop'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    log_message('Control: Invalid action');
    exit;
}

$action = $_POST['action'];
log_message("Control: Processing action: $action");
log_message("Control: Session status before: " . ($_SESSION['model_b_email_campaign_status'] ?? 'none'));

if ($action === 'pause') {
    $_SESSION['model_b_email_campaign_status'] = 'paused';
    log_message('Control: Campaign paused');
    log_message("Control: Session status after: {$_SESSION['model_b_email_campaign_status']}");
    echo json_encode(['status' => 'paused']);
} elseif ($action === 'resume') {
    $_SESSION['model_b_email_campaign_status'] = 'running';
    log_message('Control: Campaign resumed');
    log_message("Control: Session status after: {$_SESSION['model_b_email_campaign_status']}");
    echo json_encode(['status' => 'resumed']);
} elseif ($action === 'stop') {
    $_SESSION['model_b_email_campaign_status'] = 'stopped';
    log_message('Control: Campaign stopped');
    log_message("Control: Session status after: {$_SESSION['model_b_email_campaign_status']}");
    echo json_encode(['status' => 'stopped']);
}

session_write_close();
log_message('Control: Session closed');
exit;
?>
