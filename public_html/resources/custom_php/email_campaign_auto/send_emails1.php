<?php
// send_emails1.php - Send emails for a campaign with enhanced debugging
// Version: 2025-01-25-Enhanced-Debugging

require_once __DIR__ . '/vendor/autoload.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';
require_once __DIR__ . '/lib/email-headers-comparison.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Enhanced logging function
function enhanced_log($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] {$message}\n";
    
    // Log to both files
    @file_put_contents(SEND_EMAILS_LOG, $log_entry, FILE_APPEND | LOCK_EX);
    @file_put_contents(EMAIL_CAMPAIGN_LOG, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Also log to error_log for debugging
    error_log("EmailCampaign [{$level}]: {$message}");
}

function write_progress($progress_file, $progress) {
    $progress['last_updated'] = time();
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $result = @file_put_contents($progress_file, json_encode($progress, JSON_PRETTY_PRINT), LOCK_EX);
        if ($result !== false) {
            @chmod($progress_file, 0600);
            enhanced_log("Successfully wrote to progress file {$progress_file} on attempt {$attempt}");
            return true;
        }
        enhanced_log("Failed to write to progress file {$progress_file} on attempt {$attempt}", 'ERROR');
        usleep(100000);
    }
    return false;
}

function read_progress($progress_file) {
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $data = @file_get_contents($progress_file);
        if ($data !== false) {
            $progress = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $progress;
            }
        }
        enhanced_log("Failed to read progress file {$progress_file} on attempt {$attempt}", 'ERROR');
        usleep(100000);
    }
    return false;
}

function test_smtp_connection($sender_email) {
    global $emailConfigs;
    
    if (!isset($emailConfigs[$sender_email])) {
        enhanced_log("No SMTP configuration found for {$sender_email}", 'ERROR');
        return false;
    }
    
    $config = $emailConfigs[$sender_email];
    enhanced_log("Testing SMTP connection for {$sender_email}");
    enhanced_log("SMTP Host: {$config['smtp_host']}, Port: {$config['smtp_port']}");
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_encryption'];
        $mail->Port = $config['smtp_port'];
        $mail->SMTPDebug = 0; // Disable debug output
        
        // Test connection
        if ($mail->smtpConnect()) {
            enhanced_log("SMTP connection successful for {$sender_email}");
            $mail->smtpClose();
            return true;
        } else {
            enhanced_log("SMTP connection failed for {$sender_email}", 'ERROR');
            return false;
        }
    } catch (Exception $e) {
        enhanced_log("SMTP connection error for {$sender_email}: {$e->getMessage()}", 'ERROR');
        return false;
    }
}

function setup_mailer($sender_email, $sender_name) {
    global $emailConfigs;
    
    if (!isset($emailConfigs[$sender_email])) {
        enhanced_log("No SMTP configuration found for {$sender_email}", 'ERROR');
        return false;
    }
    
    $config = $emailConfigs[$sender_email];
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_encryption'];
        $mail->Port = $config['smtp_port'];
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'quoted-printable';
        $mail->XMailer = ' '; // Disable PHPMailer's default X-Mailer header
        $mail->SMTPDebug = 0; // Disable debug output for production
        
        // Initialize header manager for domain-specific headers
        $headerManager = new EmailHeaderManager();
        
        // Set the From address based on domain-specific rules
        if (strpos(strtolower($sender_email), 'thefastmode.com') !== false) {
            $mail->setFrom('abn@thefastmode.com', 'ANB');
            enhanced_log("Using hardcoded thefastmode.com sender: ANB <abn@thefastmode.com>");
        } else {
            $mail->setFrom($sender_email, $sender_name);
        }
        
        enhanced_log("PHPMailer configured for {$sender_email}");
        return $mail;
    } catch (Exception $e) {
        enhanced_log("PHPMailer configuration failed: {$e->getMessage()}", 'ERROR');
        return false;
    }
}

// Start of main execution
enhanced_log("=== SEND_EMAILS1.PHP STARTED ===");
enhanced_log("PHP Version: " . PHP_VERSION);
enhanced_log("Script arguments: " . implode(', ', $argv));

if ($argc !== 2) {
    enhanced_log("Invalid arguments, expected campaign_id", 'ERROR');
    exit(1);
}

$campaign_id = trim($argv[1]);
if (!preg_match('/^[a-z0-9]{12}$/', $campaign_id)) {
    enhanced_log("Invalid campaign ID: {$campaign_id}", 'ERROR');
    exit(1);
}

enhanced_log("Starting email campaign {$campaign_id}");

$progress_file = CAMPAIGN_DATA_DIR . "/campaign_{$campaign_id}_progress.json";
enhanced_log("Progress file: {$progress_file}");

if (!file_exists($progress_file)) {
    enhanced_log("Progress file {$progress_file} not found", 'ERROR');
    exit(1);
}

$progress = read_progress($progress_file);
if ($progress === false) {
    enhanced_log("Failed to read progress file {$progress_file}", 'ERROR');
    exit(1);
}

enhanced_log("Progress loaded - Status: {$progress['status']}, Total emails: {$progress['total_emails']}, Sent: {$progress['emails_sent']}");

// Check if campaign is stopped or completed
if (in_array($progress['status'], ['stopped', 'completed']) || $progress['completed']) {
    enhanced_log("Campaign {$campaign_id} is already {$progress['status']}, exiting");
    exit(0);
}

$recipients = $progress['recipients'];
$emails_sent = $progress['emails_sent'];
$interval = $progress['interval'];
$subject = $progress['subject'];
$content = $progress['content'];
$sender_email = $progress['sender_email'];
$sender_name = $progress['sender_name'];

enhanced_log("Campaign details - Sender: {$sender_email}, Interval: {$interval}s, Subject: {$subject}");

// Test SMTP connection first
if (!test_smtp_connection($sender_email)) {
    $progress['status'] = 'error';
    $progress['messages'][] = "SMTP connection test failed for {$sender_email}";
    write_progress($progress_file, $progress);
    enhanced_log("SMTP connection test failed, stopping campaign", 'ERROR');
    exit(1);
}

// Setup PHPMailer
$mail = setup_mailer($sender_email, $sender_name);
if (!$mail) {
    $progress['status'] = 'error';
    $progress['messages'][] = "Failed to configure SMTP for {$sender_email}";
    write_progress($progress_file, $progress);
    enhanced_log("Failed to setup mailer, stopping campaign", 'ERROR');
    exit(1);
}

$progress['status'] = 'running';
$client_type = strpos(strtolower($sender_email), 'thefastmode.com') !== false ? 'Zoho Mail' : 'Email Client';
$progress['messages'][] = "Email sending started at " . date('Y-m-d H:i:s') . " using {$client_type} headers";
write_progress($progress_file, $progress);

enhanced_log("Campaign status updated to running");

$remaining_recipients = array_slice($recipients, $emails_sent);
if (empty($remaining_recipients)) {
    $progress['status'] = 'completed';
    $progress['completed'] = true;
    $progress['messages'][] = "Campaign {$campaign_id} finished: {$emails_sent} of " . count($recipients) . " emails sent";
    write_progress($progress_file, $progress);
    enhanced_log("Campaign {$campaign_id} completed - no remaining recipients");
    exit(0);
}

enhanced_log("Starting to send emails to " . count($remaining_recipients) . " remaining recipients");

foreach ($remaining_recipients as $index => $recipient) {
    enhanced_log("Processing recipient " . ($index + 1) . " of " . count($remaining_recipients));
    
    // Check campaign status before each email
    $current_progress = read_progress($progress_file);
    if ($current_progress === false) {
        enhanced_log("Failed to read progress during sending loop", 'ERROR');
        break;
    }
    
    if ($current_progress['status'] === 'stopped') {
        enhanced_log("Campaign {$campaign_id} stopped, halting email sending");
        break;
    }
    
    if ($current_progress['status'] === 'paused') {
        enhanced_log("Campaign {$campaign_id} paused, waiting...");
        while (true) {
            sleep(5);
            $current_progress = read_progress($progress_file);
            if ($current_progress === false) {
                enhanced_log("Failed to read progress during pause", 'ERROR');
                exit(1);
            }
            
            if ($current_progress['status'] === 'stopped') {
                enhanced_log("Campaign {$campaign_id} stopped during pause");
                exit(0);
            }
            
            if ($current_progress['status'] === 'running' || $current_progress['status'] === 'pending') {
                enhanced_log("Campaign {$campaign_id} resumed");
                break;
            }
        }
    }
    
    $email = $recipient[0];
    $first_name = $recipient[1];
    
    enhanced_log("Sending email to {$email} ({$first_name})");
    
    // Personalize content
    $personalized_content = str_replace(
        ['{FirstName}', '{SenderName}'],
        [$first_name, $sender_name],
        $content
    );
    
    try {
        // Clear previous recipients
        $mail->clearAddresses();
        $mail->clearAttachments();
        
        // Set recipient
        $mail->addAddress($email, $first_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $personalized_content;
        
        // Generate a unique Message-ID for each email
        $headerManager = new EmailHeaderManager();
        $uniqueMessageId = $headerManager->generateMessageID($sender_email);
        $mail->MessageID = $uniqueMessageId;
        
        // Clear and re-add custom headers for each email
        $mail->clearCustomHeaders();
        $mail->XMailer = ' '; // Suppress PHPMailer header
        
        if (strpos(strtolower($sender_email), 'thefastmode.com') !== false) {
            // Zoho Mail headers for thefastmode.com
            $mail->addCustomHeader('X-Mailer', 'Zoho Mail');
            $mail->addCustomHeader('User-Agent', 'Zoho Mail');
            $mail->addCustomHeader('X-Priority', '3');
            $mail->addCustomHeader('Importance', 'Normal');
            $mail->addCustomHeader('X-Zoho-Virus-Status', 'Clean');
            $mail->addCustomHeader('X-Zoho-Spam-Status', 'No');
            $mail->addCustomHeader('X-ZohoMail-DKIM', 'pass');
        } else {
            // Default headers for other domains
            $mail->addCustomHeader('X-Priority', '3');
            $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
            $mail->addCustomHeader('X-Mailer', 'Microsoft Outlook 16.0');
            $mail->addCustomHeader('Importance', 'Normal');
        }
        
        // Add campaign tracking headers
        $mail->addCustomHeader('X-Campaign-ID', $campaign_id);
        $mail->addCustomHeader('X-Recipient-ID', base64_encode($email));
        
        // Send email
        $mail->send();
        
        $current_progress['emails_sent']++;
        $header_type = strpos(strtolower($sender_email), 'thefastmode.com') !== false ? 'Zoho Mail' : 'Outlook';
        $current_progress['messages'][] = "Email sent to {$email} ({$first_name}) using {$header_type} headers - Message-ID: {$uniqueMessageId}";
        enhanced_log("Email sent successfully to {$email} ({$first_name}) with {$header_type} headers");
        
    } catch (Exception $e) {
        $current_progress['messages'][] = "Failed to send email to {$email} ({$first_name}): {$e->getMessage()}";
        enhanced_log("Failed to send email to {$email} ({$first_name}): {$e->getMessage()}", 'ERROR');
    }
    
    // Update progress
    if (!write_progress($progress_file, $current_progress)) {
        enhanced_log("Failed to write progress for campaign {$campaign_id} after sending email to {$email}", 'ERROR');
        exit(1);
    }
    
    // Wait interval before next email (except for last email)
    if ($index < count($remaining_recipients) - 1) {
        enhanced_log("Waiting {$interval} seconds before next email");
        sleep($interval);
    }
}

// Final status update
$final_progress = read_progress($progress_file);
if ($final_progress !== false) {
    if ($final_progress['status'] !== 'stopped') {
        $final_progress['status'] = 'completed';
        $final_progress['completed'] = true;
        $client_type = strpos(strtolower($sender_email), 'thefastmode.com') !== false ? 'Zoho Mail' : 'Email Client';
        $final_progress['messages'][] = "Campaign {$campaign_id} finished: {$final_progress['emails_sent']} of " . count($recipients) . " emails sent using {$client_type} headers";
        write_progress($progress_file, $final_progress);
    }
}

enhanced_log("=== CAMPAIGN {$campaign_id} SENDING PROCESS COMPLETED ===");
exit(0);
?>
