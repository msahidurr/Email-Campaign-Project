<?php
// send_emails1.php - Send emails for a campaign using PHPMailer
// Version: 2025-01-25-Dynamic-Paths

require_once __DIR__ . '/vendor/autoload.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function write_progress($progress_file, $progress) {
    $progress['last_updated'] = time();
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $result = @file_put_contents($progress_file, json_encode($progress, JSON_PRETTY_PRINT), LOCK_EX);
        if ($result !== false) {
            @chmod($progress_file, 0600);
            log_message("Successfully wrote to progress file {$progress_file} on attempt {$attempt}", SEND_EMAILS_LOG);
            return true;
        }
        log_message("Failed to write to progress file {$progress_file} on attempt {$attempt}", SEND_EMAILS_LOG);
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
        log_message("Failed to read progress file {$progress_file} on attempt {$attempt}", SEND_EMAILS_LOG);
        usleep(100000);
    }
    return false;
}

function setup_mailer($sender_email) {
    global $emailConfigs;
    
    if (!isset($emailConfigs[$sender_email])) {
        log_message("No SMTP configuration found for {$sender_email}", SEND_EMAILS_LOG);
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
        
        // Sender
        $mail->setFrom($sender_email, $config['sender_name']);
        
        log_message("PHPMailer configured for {$sender_email}", SEND_EMAILS_LOG);
        return $mail;
    } catch (Exception $e) {
        log_message("PHPMailer configuration failed: {$e->getMessage()}", SEND_EMAILS_LOG);
        return false;
    }
}

if ($argc !== 2) {
    log_message("Invalid arguments, expected campaign_id", SEND_EMAILS_LOG);
    exit(1);
}

$campaign_id = trim($argv[1]);
if (!preg_match('/^[a-z0-9]{12}$/', $campaign_id)) {
    log_message("Invalid campaign ID: {$campaign_id}", SEND_EMAILS_LOG);
    exit(1);
}

log_message("Starting email campaign {$campaign_id}", SEND_EMAILS_LOG);

$progress_file = CAMPAIGN_DATA_DIR . "/campaign_{$campaign_id}_progress.json";
if (!file_exists($progress_file)) {
    log_message("Progress file {$progress_file} not found", SEND_EMAILS_LOG);
    exit(1);
}

$progress = read_progress($progress_file);
if ($progress === false) {
    log_message("Failed to read progress file {$progress_file}", SEND_EMAILS_LOG);
    exit(1);
}

// Check if campaign is stopped or completed
if (in_array($progress['status'], ['stopped', 'completed']) || $progress['completed']) {
    log_message("Campaign {$campaign_id} is already {$progress['status']}, exiting", SEND_EMAILS_LOG);
    exit(0);
}

$recipients = $progress['recipients'];
$emails_sent = $progress['emails_sent'];
$interval = $progress['interval'];
$subject = $progress['subject'];
$content = $progress['content'];
$sender_email = $progress['sender_email'];
$sender_name = $progress['sender_name'];

// Setup PHPMailer
$mail = setup_mailer($sender_email);
if (!$mail) {
    $progress['status'] = 'error';
    $progress['messages'][] = "Failed to configure SMTP for {$sender_email}";
    write_progress($progress_file, $progress);
    exit(1);
}

$progress['status'] = 'running';
$progress['messages'][] = "Email sending started at " . date('Y-m-d H:i:s');
write_progress($progress_file, $progress);

$remaining_recipients = array_slice($recipients, $emails_sent);
if (empty($remaining_recipients)) {
    $progress['status'] = 'completed';
    $progress['completed'] = true;
    $progress['messages'][] = "Campaign {$campaign_id} finished: {$emails_sent} of " . count($recipients) . " emails sent";
    write_progress($progress_file, $progress);
    log_message("Campaign {$campaign_id} completed - no remaining recipients", SEND_EMAILS_LOG);
    exit(0);
}

foreach ($remaining_recipients as $index => $recipient) {
    // Check campaign status before each email
    $current_progress = read_progress($progress_file);
    if ($current_progress === false) {
        log_message("Failed to read progress during sending loop", SEND_EMAILS_LOG);
        break;
    }
    
    if ($current_progress['status'] === 'stopped') {
        log_message("Campaign {$campaign_id} stopped, halting email sending", SEND_EMAILS_LOG);
        break;
    }
    
    if ($current_progress['status'] === 'paused') {
        log_message("Campaign {$campaign_id} paused, waiting...", SEND_EMAILS_LOG);
        // Wait and check again
        while (true) {
            sleep(5);
            $current_progress = read_progress($progress_file);
            if ($current_progress === false) {
                log_message("Failed to read progress during pause", SEND_EMAILS_LOG);
                exit(1);
            }
            
            if ($current_progress['status'] === 'stopped') {
                log_message("Campaign {$campaign_id} stopped during pause", SEND_EMAILS_LOG);
                exit(0);
            }
            
            if ($current_progress['status'] === 'running' || $current_progress['status'] === 'pending') {
                log_message("Campaign {$campaign_id} resumed", SEND_EMAILS_LOG);
                break;
            }
        }
    }
    
    $email = $recipient[0];
    $first_name = $recipient[1];
    
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
        
        // Send email
        $mail->send();
        
        $current_progress['emails_sent']++;
        $current_progress['messages'][] = "Email sent to {$email} ({$first_name})";
        log_message("Email sent to {$email} ({$first_name}) for campaign {$campaign_id}", SEND_EMAILS_LOG);
        
    } catch (Exception $e) {
        $current_progress['messages'][] = "Failed to send email to {$email} ({$first_name}): {$e->getMessage()}";
        log_message("Failed to send email to {$email} ({$first_name}) for campaign {$campaign_id}: {$e->getMessage()}", SEND_EMAILS_LOG);
    }
    
    // Update progress
    if (!write_progress($progress_file, $current_progress)) {
        log_message("Failed to write progress for campaign {$campaign_id} after sending email to {$email}", SEND_EMAILS_LOG);
        exit(1);
    }
    
    // Wait interval before next email (except for last email)
    if ($index < count($remaining_recipients) - 1) {
        log_message("Waiting {$interval} seconds before next email", SEND_EMAILS_LOG);
        sleep($interval);
    }
}

// Final status update
$final_progress = read_progress($progress_file);
if ($final_progress !== false) {
    if ($final_progress['status'] !== 'stopped') {
        $final_progress['status'] = 'completed';
        $final_progress['completed'] = true;
        $final_progress['messages'][] = "Campaign {$campaign_id} finished: {$final_progress['emails_sent']} of " . count($recipients) . " emails sent";
        write_progress($progress_file, $final_progress);
    }
}

log_message("Campaign {$campaign_id} sending process completed", SEND_EMAILS_LOG);
exit(0);
?>
