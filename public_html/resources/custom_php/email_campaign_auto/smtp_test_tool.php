<?php
// smtp_test_tool.php - Test SMTP configurations
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: text/html; charset=UTF-8');

function test_smtp_detailed($email, $config) {
    $results = [];
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_encryption'];
        $mail->Port = $config['smtp_port'];
        $mail->SMTPDebug = 2; // Enable verbose debug output
        $mail->Timeout = 30;
        
        // Capture debug output
        ob_start();
        $connected = $mail->smtpConnect();
        $debug_output = ob_get_clean();
        
        if ($connected) {
            $results['status'] = 'success';
            $results['message'] = 'SMTP connection successful';
            $mail->smtpClose();
        } else {
            $results['status'] = 'error';
            $results['message'] = 'SMTP connection failed';
        }
        
        $results['debug'] = $debug_output;
        
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['message'] = $e->getMessage();
        $results['debug'] = ob_get_clean();
    }
    
    return $results;
}

if ($_POST && isset($_POST['test_email'])) {
    $test_email = $_POST['test_email'];
    if (isset($emailConfigs[$test_email])) {
        $test_results = test_smtp_detailed($test_email, $emailConfigs[$test_email]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Test Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h3 { margin-top: 0; color: #333; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select, button { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; cursor: pointer; }
        button:hover { background: #0056b3; }
        .debug-output { background: #000; color: #0f0; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; }
        .config-display { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .alert { padding: 15px; margin: 15px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 SMTP Test Tool</h1>
        
        <div class="section">
            <h3>Test SMTP Configuration</h3>
            <form method="post">
                <div class="form-group">
                    <label>Select Email Configuration to Test:</label>
                    <select name="test_email" required>
                        <option value="">Choose an email...</option>
                        <?php foreach ($emailConfigs as $email => $config): ?>
                            <option value="<?php echo htmlspecialchars($email); ?>" <?php echo (isset($_POST['test_email']) && $_POST['test_email'] === $email) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($email); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Test SMTP Connection</button>
            </form>
        </div>
        
        <?php if (isset($_POST['test_email']) && isset($emailConfigs[$_POST['test_email']])): ?>
            <div class="section">
                <h3>Configuration for <?php echo htmlspecialchars($_POST['test_email']); ?></h3>
                <div class="config-display">
                    <strong>SMTP Host:</strong> <?php echo htmlspecialchars($emailConfigs[$_POST['test_email']]['smtp_host']); ?><br>
                    <strong>SMTP Port:</strong> <?php echo htmlspecialchars($emailConfigs[$_POST['test_email']]['smtp_port']); ?><br>
                    <strong>Encryption:</strong> <?php echo htmlspecialchars($emailConfigs[$_POST['test_email']]['smtp_encryption']); ?><br>
                    <strong>Username:</strong> <?php echo htmlspecialchars($emailConfigs[$_POST['test_email']]['smtp_username']); ?><br>
                    <strong>Password:</strong> <?php echo empty($emailConfigs[$_POST['test_email']]['smtp_password']) || $emailConfigs[$_POST['test_email']]['smtp_password'] === 'YOUR_ACTUAL_ZOHO_PASSWORD_HERE' ? '<span class="status-error">NOT SET</span>' : '<span class="status-ok">SET</span>'; ?><br>
                    <strong>Sender Name:</strong> <?php echo htmlspecialchars($emailConfigs[$_POST['test_email']]['sender_name']); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($test_results)): ?>
            <div class="section">
                <h3>Test Results</h3>
                <div class="alert <?php echo $test_results['status'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <strong>Status:</strong> <?php echo $test_results['status'] === 'success' ? '✅ SUCCESS' : '❌ FAILED'; ?><br>
                    <strong>Message:</strong> <?php echo htmlspecialchars($test_results['message']); ?>
                </div>
                
                <?php if (!empty($test_results['debug'])): ?>
                    <h4>Debug Output:</h4>
                    <div class="debug-output"><?php echo htmlspecialchars($test_results['debug']); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="section">
            <h3>📋 Configuration Instructions</h3>
            <div class="alert alert-warning">
                <strong>For thefastmode.com emails:</strong><br>
                1. You need a real Zoho Mail account for <code>abn@thefastmode.com</code><br>
                2. Update the password in <code>config.php</code><br>
                3. Make sure the SMTP username matches the actual email account<br>
                4. The "From" address must be authorized by your Zoho account
            </div>
            
            <h4>Common Issues:</h4>
            <ul>
                <li><strong>"MAIL FROM command failed"</strong> - The From address is not authorized by your SMTP server</li>
                <li><strong>"Authentication failed"</strong> - Wrong username/password</li>
                <li><strong>"Connection timeout"</strong> - Wrong host/port or firewall blocking</li>
                <li><strong>"TLS/SSL errors"</strong> - Wrong encryption setting</li>
            </ul>
        </div>
    </div>
</body>
</html>
