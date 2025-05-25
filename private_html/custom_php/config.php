<?php
// Configuration file for email campaign system
// Version: 2025-01-25-Fixed-Session-Directory

// Get the real path of the current directory
$current_dir = realpath(__DIR__);
$base_path = dirname($current_dir); // Gets the parent directory of custom_php

// Try to detect the correct paths
if (strpos($current_dir, 'private_html') !== false) {
    // We're in private_html/custom_php
    $private_path = dirname($current_dir);
    $public_path = dirname($private_path) . '/public_html';
} else {
    // Fallback - assume standard structure
    $private_path = $base_path;
    $public_path = $base_path . '/public_html';
}

// Define all paths dynamically
define('BASE_PATH', $base_path);
define('PUBLIC_PATH', $public_path);
define('PRIVATE_PATH', $private_path);
define('CUSTOM_PHP_PATH', $current_dir);

// Try to find the campaign auto path
$possible_campaign_paths = [
    PUBLIC_PATH . '/resources/custom_php/email_campaign_auto',
    $public_path . '/resources/custom_php/email_campaign_auto',
    dirname($current_dir) . '/public_html/resources/custom_php/email_campaign_auto'
];

$campaign_auto_path = null;
foreach ($possible_campaign_paths as $path) {
    if (is_dir($path)) {
        $campaign_auto_path = $path;
        break;
    }
}

if (!$campaign_auto_path) {
    $campaign_auto_path = PUBLIC_PATH . '/resources/custom_php/email_campaign_auto';
}

define('CAMPAIGN_AUTO_PATH', $campaign_auto_path);

// Data directories - use absolute paths with fallbacks
$data_base = PRIVATE_PATH;
if (!is_writable($data_base)) {
    // Try alternative locations
    $alternatives = [
        sys_get_temp_dir() . '/email_campaign',
        '/tmp/email_campaign',
        dirname(__FILE__) . '/data'
    ];
    
    foreach ($alternatives as $alt) {
        if (is_dir(dirname($alt)) && is_writable(dirname($alt))) {
            $data_base = $alt;
            break;
        }
    }
}

define('CAMPAIGN_DATA_DIR', $data_base . '/campaign_data');
define('SESSION_PATH', $data_base . '/sessions');
define('LOG_PATH', $data_base);

// Log files
define('EMAIL_CAMPAIGN_LOG', LOG_PATH . '/email_campaign.log');
define('SEND_EMAILS_LOG', LOG_PATH . '/send_emails1.log');
define('PHP_ERRORS_LOG', LOG_PATH . '/php_errors.log');

// Scripts
define('SEND_EMAILS_SCRIPT', CAMPAIGN_AUTO_PATH . '/send_emails1.php');

// Helper function to ensure directory exists with better error handling
function ensure_directory($path, $permissions = 0755) {
    if (is_dir($path)) {
        return is_writable($path);
    }
    
    // Try to create the directory
    if (@mkdir($path, $permissions, true)) {
        @chmod($path, $permissions);
        return true;
    }
    
    // If mkdir failed, try alternative approaches
    $parent = dirname($path);
    if (!is_dir($parent)) {
        if (!ensure_directory($parent, $permissions)) {
            return false;
        }
    }
    
    // Try again with different permissions
    if (@mkdir($path, 0777, true)) {
        @chmod($path, $permissions);
        return true;
    }
    
    return false;
}

// Helper function to log messages with fallback
function log_message($message, $log_file = null) {
    if ($log_file === null) {
        $log_file = defined('EMAIL_CAMPAIGN_LOG') ? EMAIL_CAMPAIGN_LOG : sys_get_temp_dir() . '/email_campaign.log';
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    
    // Try to write to the specified log file
    if (@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) === false) {
        // Fallback to error_log if file writing fails
        error_log("Email Campaign: $message");
    }
}

// Helper function to get relative URL path
function getRelativeURL($file_path) {
    $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if (empty($document_root)) {
        return $file_path;
    }
    
    $relative_path = str_replace($document_root, '', $file_path);
    return $relative_path;
}

// Create directories with better error handling
$directories = [
    LOG_PATH => 0755,
    CAMPAIGN_DATA_DIR => 0755,
    SESSION_PATH => 0700
];

$creation_errors = [];
foreach ($directories as $dir => $perms) {
    if (!ensure_directory($dir, $perms)) {
        $creation_errors[] = $dir;
        log_message("Failed to create directory: $dir");
    } else {
        log_message("Successfully created/verified directory: $dir");
    }
}

// If we have creation errors, try alternative session path
if (in_array(SESSION_PATH, $creation_errors)) {
    $alternative_session_paths = [
        sys_get_temp_dir() . '/email_campaign_sessions',
        '/tmp/email_campaign_sessions',
        dirname(__FILE__) . '/sessions'
    ];
    
    foreach ($alternative_session_paths as $alt_path) {
        if (ensure_directory($alt_path, 0700)) {
            // Redefine the session path constant (PHP 8+ allows this)
            if (defined('SESSION_PATH')) {
                // For older PHP versions, we'll use a global variable
                $GLOBALS['ALTERNATIVE_SESSION_PATH'] = $alt_path;
            }
            log_message("Using alternative session path: $alt_path");
            break;
        }
    }
}

// Function to get the actual session path (with fallback)
function get_session_path() {
    if (isset($GLOBALS['ALTERNATIVE_SESSION_PATH'])) {
        return $GLOBALS['ALTERNATIVE_SESSION_PATH'];
    }
    return defined('SESSION_PATH') ? SESSION_PATH : sys_get_temp_dir();
}

// SMTP Email configurations
$emailConfigs = [
    'ray.sharma10@gmail.com' => [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_username' => 'ray.sharma10@gmail.com',
        'smtp_password' => 'your_app_password_here', // Use App Password for Gmail
        'sender_name' => 'Ray Sharma'
    ],
    'support@yourdomain.com' => [
        'smtp_host' => 'mail.yourdomain.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_username' => 'support@yourdomain.com',
        'smtp_password' => 'your_password_here',
        'sender_name' => 'Support Team'
    ],
    'noreply@yourdomain.com' => [
        'smtp_host' => 'mail.yourdomain.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_username' => 'noreply@yourdomain.com',
        'smtp_password' => 'your_password_here',
        'sender_name' => 'No Reply'
    ]
];

// Log the configuration for debugging
log_message("Configuration loaded:");
log_message("BASE_PATH: " . BASE_PATH);
log_message("PUBLIC_PATH: " . PUBLIC_PATH);
log_message("PRIVATE_PATH: " . PRIVATE_PATH);
log_message("CAMPAIGN_DATA_DIR: " . CAMPAIGN_DATA_DIR);
log_message("SESSION_PATH: " . get_session_path());
log_message("LOG_PATH: " . LOG_PATH);

// Check if directories are writable
$writable_status = [
    'CAMPAIGN_DATA_DIR' => is_writable(CAMPAIGN_DATA_DIR) ? 'YES' : 'NO',
    'SESSION_PATH' => is_writable(get_session_path()) ? 'YES' : 'NO',
    'LOG_PATH' => is_writable(LOG_PATH) ? 'YES' : 'NO'
];

log_message("Directory writable status: " . json_encode($writable_status));
?>
