<?php
// get_progress.php - Retrieves the progress of an email campaign
// Version: 2025-01-25-Dynamic-Paths

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';

header('Content-Type: application/json');

$version = '2025-01-25-Dynamic-Paths';
log_message("Starting get_progress.php (Version: {$version})");

function sanitize_string($string) {
    // Remove control characters (0x00-0x1F, 0x7F)
    $string = preg_replace('/[\x00-\x1F\x7F]/u', '', $string);
    // Ensure UTF-8 encoding
    return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
}

if (!isset($_GET['campaign_id'])) {
    log_message("Campaign ID not provided");
    echo json_encode(['error' => 'Campaign ID not provided']);
    exit;
}

$campaign_id = sanitize_string($_GET['campaign_id']);
$progress_file = CAMPAIGN_DATA_DIR . "/campaign_{$campaign_id}_progress.json";

log_message("Checking progress file {$progress_file} for campaign ID: {$campaign_id}");

if (!file_exists($progress_file)) {
    log_message("Campaign not found for ID: {$campaign_id}");
    echo json_encode(['error' => "Campaign {$campaign_id} not found"]);
    exit;
}

$fp = @fopen($progress_file, 'r');
if (!$fp) {
    log_message("Failed to open progress file {$progress_file}");
    echo json_encode(['error' => "Failed to read campaign data for {$campaign_id}"]);
    exit;
}

if (flock($fp, LOCK_SH)) {
    $data = fread($fp, filesize($progress_file));
    flock($fp, LOCK_UN);
    fclose($fp);
    $progress_data = json_decode($data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Failed to parse progress file {$progress_file}: " . json_last_error_msg());
        echo json_encode(['error' => "Failed to parse campaign data for {$campaign_id}"]);
        exit;
    }
} else {
    fclose($fp);
    log_message("Failed to lock progress file {$progress_file}");
    echo json_encode(['error' => "Failed to read campaign data for {$campaign_id}"]);
    exit;
}

// Sanitize output data
array_walk_recursive($progress_data, function (&$value) {
    if (is_string($value)) {
        $value = sanitize_string($value);
    }
});

echo json_encode($progress_data);
?>
