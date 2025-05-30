<?php
// debug_campaign.php - Debug tool for campaign issues
// Version: 2025-01-25-Debug-Tool

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';

header('Content-Type: text/html; charset=UTF-8');

function check_php_executable() {
    $php_paths = ['php', '/usr/bin/php', '/usr/local/bin/php', '/opt/php/bin/php'];
    $working_php = null;
    
    foreach ($php_paths as $php) {
        $output = shell_exec("{$php} -v 2>/dev/null");
        if ($output && strpos($output, 'PHP') !== false) {
            $working_php = $php;
            break;
        }
    }
    
    return $working_php;
}

function test_file_permissions() {
    $paths_to_check = [
        'Campaign Data Dir' => CAMPAIGN_DATA_DIR,
        'Session Path' => get_session_path(),
        'Log Path' => LOG_PATH,
        'Send Emails Script' => SEND_EMAILS_SCRIPT
    ];
    
    $results = [];
    foreach ($paths_to_check as $name => $path) {
        $results[$name] = [
            'path' => $path,
            'exists' => file_exists($path),
            'readable' => is_readable($path),
            'writable' => is_writable($path),
            'executable' => is_executable($path)
        ];
    }
    
    return $results;
}

function test_smtp_config() {
    global $emailConfigs;
    $results = [];
    
    foreach ($emailConfigs as $email => $config) {
        $results[$email] = [
            'host' => $config['smtp_host'],
            'port' => $config['smtp_port'],
            'encryption' => $config['smtp_encryption'],
            'username' => $config['smtp_username']
        ];
    }
    
    return $results;
}

$php_executable = check_php_executable();
$file_permissions = test_file_permissions();
$smtp_configs = test_smtp_config();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Campaign Debug Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h3 { margin-top: 0; color: #333; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
        .log-output { background: #000; color: #0f0; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Email Campaign Debug Tool</h1>
        
        <div class="section">
            <h3>📋 System Information</h3>
            <table>
                <tr><td><strong>PHP Version:</strong></td><td><?php echo PHP_VERSION; ?></td></tr>
                <tr><td><strong>Server Software:</strong></td><td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td></tr>
                <tr><td><strong>PHP Executable:</strong></td><td class="<?php echo $php_executable ? 'status-ok' : 'status-error'; ?>"><?php echo $php_executable ?: 'NOT FOUND'; ?></td></tr>
                <tr><td><strong>Current User:</strong></td><td><?php echo get_current_user(); ?></td></tr>
                <tr><td><strong>Working Directory:</strong></td><td><?php echo getcwd(); ?></td></tr>
            </table>
        </div>
        
        <div class="section">
            <h3>📁 File Permissions</h3>
            <table>
                <tr><th>Path</th><th>Exists</th><th>Readable</th><th>Writable</th><th>Executable</th></tr>
                <?php foreach ($file_permissions as $name => $info): ?>
                <tr>
                    <td><strong><?php echo $name; ?></strong><br><small><?php echo $info['path']; ?></small></td>
                    <td class="<?php echo $info['exists'] ? 'status-ok' : 'status-error'; ?>"><?php echo $info['exists'] ? 'YES' : 'NO'; ?></td>
                    <td class="<?php echo $info['readable'] ? 'status-ok' : 'status-error'; ?>"><?php echo $info['readable'] ? 'YES' : 'NO'; ?></td>
                    <td class="<?php echo $info['writable'] ? 'status-ok' : 'status-error'; ?>"><?php echo $info['writable'] ? 'YES' : 'NO'; ?></td>
                    <td class="<?php echo $info['executable'] ? 'status-ok' : 'status-warning'; ?>"><?php echo $info['executable'] ? 'YES' : 'NO'; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div class="section">
            <h3>📧 SMTP Configurations</h3>
            <table>
                <tr><th>Email</th><th>Host</th><th>Port</th><th>Encryption</th><th>Username</th></tr>
                <?php foreach ($smtp_configs as $email => $config): ?>
                <tr>
                    <td><?php echo htmlspecialchars($email); ?></td>
                    <td><?php echo htmlspecialchars($config['host']); ?></td>
                    <td><?php echo htmlspecialchars($config['port']); ?></td>
                    <td><?php echo htmlspecialchars($config['encryption']); ?></td>
                    <td><?php echo htmlspecialchars($config['username']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div class="section">
            <h3>🧪 Test Commands</h3>
            <p>Test if the send_emails1.php script can be executed:</p>
            <div class="code">
                <?php echo $php_executable; ?> <?php echo SEND_EMAILS_SCRIPT; ?> test123456
            </div>
            
            <button onclick="testScript()">Test Script Execution</button>
            <button onclick="viewLogs()">View Recent Logs</button>
            <button onclick="clearLogs()">Clear Logs</button>
            
            <div id="test-output" class="log-output" style="display: none;"></div>
        </div>
        
        <div class="section">
            <h3>📊 Recent Log Entries</h3>
            <div class="log-output">
                <?php
                $log_files = [EMAIL_CAMPAIGN_LOG, SEND_EMAILS_LOG];
                foreach ($log_files as $log_file) {
                    if (file_exists($log_file)) {
                        echo "<strong>" . basename($log_file) . ":</strong>\n";
                        $lines = file($log_file);
                        $recent_lines = array_slice($lines, -10);
                        echo htmlspecialchars(implode('', $recent_lines));
                        echo "\n" . str_repeat('-', 50) . "\n";
                    }
                }
                ?>
            </div>
        </div>
    </div>
    
    <script>
        function testScript() {
            const output = document.getElementById('test-output');
            output.style.display = 'block';
            output.innerHTML = 'Testing script execution...';
            
            fetch('debug_test_script.php')
                .then(response => response.text())
                .then(data => {
                    output.innerHTML = data;
                })
                .catch(error => {
                    output.innerHTML = 'Error: ' + error;
                });
        }
        
        function viewLogs() {
            const output = document.getElementById('test-output');
            output.style.display = 'block';
            output.innerHTML = 'Loading logs...';
            
            fetch('debug_view_logs.php')
                .then(response => response.text())
                .then(data => {
                    output.innerHTML = data;
                })
                .catch(error => {
                    output.innerHTML = 'Error: ' + error;
                });
        }
        
        function clearLogs() {
            if (confirm('Are you sure you want to clear all logs?')) {
                fetch('debug_clear_logs.php')
                    .then(response => response.text())
                    .then(data => {
                        alert('Logs cleared');
                        location.reload();
                    });
            }
        }
    </script>
</body>
</html>
