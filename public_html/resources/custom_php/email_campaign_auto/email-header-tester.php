<?php
// email-header-tester.php - Test and compare email headers
// This tool helps you see the difference between PHP app and email client headers

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';
require_once __DIR__ . '/lib/email-headers-comparison.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Header Comparison Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .comparison { display: flex; gap: 20px; margin: 20px 0; }
        .column { flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .column h3 { margin-top: 0; color: #333; }
        .headers { background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; white-space: pre-wrap; }
        .difference { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .improvement { background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Email Header Comparison Tool</h1>
        <p>This tool shows the difference between emails sent via PHP and regular email clients.</p>
        
        <form method="post">
            <div class="form-group">
                <label>Sender Email:</label>
                <select name="sender_email">
                    <?php foreach ($emailConfigs as $email => $config): ?>
                        <option value="<?php echo htmlspecialchars($email); ?>" <?php echo (isset($_POST['sender_email']) && $_POST['sender_email'] === $email) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($config['sender_name'] . ' <' . $email . '>'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Sender Name:</label>
                <input type="text" name="sender_name" value="<?php echo htmlspecialchars($_POST['sender_name'] ?? 'John Doe'); ?>">
            </div>
            
            <div class="form-group">
                <label>Email Client to Simulate:</label>
                <select name="client_type">
                    <option value="outlook" <?php echo (isset($_POST['client_type']) && $_POST['client_type'] === 'outlook') ? 'selected' : ''; ?>>Microsoft Outlook</option>
                    <option value="thunderbird" <?php echo (isset($_POST['client_type']) && $_POST['client_type'] === 'thunderbird') ? 'selected' : ''; ?>>Mozilla Thunderbird</option>
                    <option value="apple_mail" <?php echo (isset($_POST['client_type']) && $_POST['client_type'] === 'apple_mail') ? 'selected' : ''; ?>>Apple Mail</option>
                    <option value="gmail_web" <?php echo (isset($_POST['client_type']) && $_POST['client_type'] === 'gmail_web') ? 'selected' : ''; ?>>Gmail Web</option>
                </select>
            </div>
            
            <button type="submit">Generate Header Comparison</button>
        </form>
        
        <?php if ($_POST): ?>
            <?php
            $headerManager = new EmailHeaderManager();
            $sender_email = $_POST['sender_email'];
            $sender_name = $_POST['sender_name'];
            $client_type = $_POST['client_type'];
            
            // Generate improved headers
            $clientHeaders = $headerManager->getEmailClientHeaders($sender_email, $sender_name, $client_type);
            
            // Basic PHP headers
            $basicHeaders = [
                'Date' => date('r'),
                'From' => $sender_email,
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Mailer' => 'PHP/' . PHP_VERSION
            ];
            ?>
            
            <div class="comparison">
                <div class="column">
                    <h3>❌ Basic PHP Headers (Before)</h3>
                    <div class="headers"><?php
                        foreach ($basicHeaders as $key => $value) {
                            echo htmlspecialchars("{$key}: {$value}\n");
                        }
                    ?></div>
                    
                    <div class="difference">
                        <strong>Issues:</strong><br>
                        • No Message-ID (poor threading)<br>
                        • Identifies as PHP (spam filters don't like this)<br>
                        • Missing priority headers<br>
                        • No authentication hints<br>
                        • Basic content encoding
                    </div>
                </div>
                
                <div class="column">
                    <h3>✅ Email Client Headers (After)</h3>
                    <div class="headers"><?php
                        foreach ($clientHeaders as $key => $value) {
                            echo htmlspecialchars("{$key}: {$value}\n");
                        }
                    ?></div>
                    
                    <div class="improvement">
                        <strong>Improvements:</strong><br>
                        • Proper Message-ID for threading<br>
                        • Looks like real email client<br>
                        • Priority and importance headers<br>
                        • Quoted-printable encoding<br>
                        • Threading support<br>
                        • Better deliverability
                    </div>
                </div>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>🔍 Key Differences Explained</h3>
                
                <h4>1. Message-ID</h4>
                <p><strong>Email Client:</strong> <code><?php echo htmlspecialchars($clientHeaders['Message-ID']); ?></code></p>
                <p><strong>Basic PHP:</strong> <em>Missing (causes threading issues)</em></p>
                
                <h4>2. X-Mailer Identification</h4>
                <p><strong>Email Client:</strong> <code><?php echo htmlspecialchars($clientHeaders['X-Mailer'] ?? 'Professional Email Client'); ?></code></p>
                <p><strong>Basic PHP:</strong> <code>PHP/<?php echo PHP_VERSION; ?></code> (red flag for spam filters)</p>
                
                <h4>3. Content Encoding</h4>
                <p><strong>Email Client:</strong> <code>quoted-printable</code> (standard for email clients)</p>
                <p><strong>Basic PHP:</strong> <em>Usually none or base64</em></p>
                
                <h4>4. Priority Headers</h4>
                <p><strong>Email Client:</strong> Includes X-Priority, X-MSMail-Priority, Importance</p>
                <p><strong>Basic PHP:</strong> <em>Missing</em></p>
                
                <h4>5. Threading Support</h4>
                <p><strong>Email Client:</strong> Thread-Topic, Thread-Index for conversation threading</p>
                <p><strong>Basic PHP:</strong> <em>Missing</em></p>
            </div>
            
            <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #007bff;">
                <h3>📈 Deliverability Impact</h3>
                <p>Using email client-style headers can significantly improve:</p>
                <ul>
                    <li><strong>Spam Filter Scores:</strong> Emails look more legitimate</li>
                    <li><strong>Threading:</strong> Proper Message-ID enables conversation threading</li>
                    <li><strong>Client Compatibility:</strong> Better display in various email clients</li>
                    <li><strong>Authentication:</strong> Proper headers support DKIM/SPF validation</li>
                    <li><strong>User Experience:</strong> Recipients see professional-looking email metadata</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
