<?php
// clear_logs.php - Clear all log files
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';

header('Content-Type: text/plain');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

$log_files = [
    EMAIL_CAMPAIGN_LOG,
    SEND_EMAILS_LOG,
    PHP_ERRORS_LOG
];

$cleared = 0;
foreach ($log_files as $log_file) {
    if (file_exists($log_file)) {
        if (@file_put_contents($log_file, '') !== false) {
            $cleared++;
        }
    }
}

echo "Cleared {$cleared} log files";
?>
