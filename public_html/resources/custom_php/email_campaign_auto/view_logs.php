<?php
// view_logs.php - View campaign logs in real-time
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';

header('Content-Type: text/html; charset=UTF-8');

function get_log_content($log_file, $lines = 50) {
    if (!file_exists($log_file)) {
        return "Log file not found: " . basename($log_file);
    }
    
    $content = file($log_file);
    if ($content === false) {
        return "Could not read log file: " . basename($log_file);
    }
    
    // Get last N lines
    $recent_lines = array_slice($content, -$lines);
    return implode('', $recent_lines);
}

$refresh = isset($_GET['refresh']) ? (int)$_GET['refresh'] : 0;
$lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 50;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Logs Viewer</title>
    <?php if ($refresh > 0): ?>
    <meta http-equiv="refresh" content="<?php echo $refresh; ?>">
    <?php endif; ?>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .log-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .log-section h3 { margin-top: 0; color: #333; }
        .log-content { background: #000; color: #0f0; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px; max-height: 500px; overflow-y: auto; white-space: pre-wrap; }
        .controls { margin: 20px 0; }
        .controls a, .controls button { display: inline-block; padding: 8px 16px; margin: 5px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .controls a:hover, .controls button:hover { background: #0056b3; }
        .error-line { color: #ff6b6b; }
        .warning-line { color: #ffa500; }
        .success-line { color: #4caf50; }
        .timestamp { color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Campaign Logs Viewer</h1>
        
        <div class="controls">
            <a href="?lines=50">Last 50 lines</a>
            <a href="?lines=100">Last 100 lines</a>
            <a href="?lines=200">Last 200 lines</a>
            <a href="?refresh=5&lines=<?php echo $lines; ?>">Auto-refresh (5s)</a>
            <a href="?refresh=10&lines=<?php echo $lines; ?>">Auto-refresh (10s)</a>
            <a href="?lines=<?php echo $lines; ?>">Stop refresh</a>
            <button onclick="clearLogs()">Clear All Logs</button>
        </div>
        
        <?php if ($refresh > 0): ?>
        <div style="background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0;">
            🔄 Auto-refreshing every <?php echo $refresh; ?> seconds... <a href="?lines=<?php echo $lines; ?>">Stop</a>
        </div>
        <?php endif; ?>
        
        <div class="log-section">
            <h3>📧 Email Campaign Log</h3>
            <div class="log-content" id="campaign-log"><?php echo htmlspecialchars(get_log_content(EMAIL_CAMPAIGN_LOG, $lines)); ?></div>
        </div>
        
        <div class="log-section">
            <h3>📤 Send Emails Log</h3>
            <div class="log-content" id="send-log"><?php echo htmlspecialchars(get_log_content(SEND_EMAILS_LOG, $lines)); ?></div>
        </div>
        
        <div class="log-section">
            <h3>🐛 PHP Error Log</h3>
            <div class="log-content" id="error-log"><?php echo htmlspecialchars(get_log_content(PHP_ERRORS_LOG, $lines)); ?></div>
        </div>
        
        <?php
        // Check for recent campaign files
        $campaign_files = glob(CAMPAIGN_DATA_DIR . '/campaign_*_progress.json');
        if (!empty($campaign_files)) {
            // Sort by modification time, newest first
            usort($campaign_files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            echo '<div class="log-section">';
            echo '<h3>📊 Recent Campaign Progress Files</h3>';
            echo '<table style="width: 100%; border-collapse: collapse;">';
            echo '<tr style="background: #f8f9fa;"><th style="padding: 8px; border: 1px solid #ddd;">Campaign ID</th><th style="padding: 8px; border: 1px solid #ddd;">Status</th><th style="padding: 8px; border: 1px solid #ddd;">Emails Sent</th><th style="padding: 8px; border: 1px solid #ddd;">Last Updated</th><th style="padding: 8px; border: 1px solid #ddd;">Actions</th></tr>';
            
            foreach (array_slice($campaign_files, 0, 10) as $file) {
                $content = @file_get_contents($file);
                if ($content) {
                    $data = json_decode($content, true);
                    if ($data) {
                        $campaign_id = basename($file, '_progress.json');
                        $campaign_id = str_replace('campaign_', '', $campaign_id);
                        echo '<tr>';
                        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($campaign_id) . '</td>';
                        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($data['status'] ?? 'unknown') . '</td>';
                        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($data['emails_sent'] ?? 0) . ' / ' . htmlspecialchars($data['total_emails'] ?? 0) . '</td>';
                        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . date('Y-m-d H:i:s', $data['last_updated'] ?? 0) . '</td>';
                        echo '<td style="padding: 8px; border: 1px solid #ddd;"><a href="?campaign=' . urlencode($campaign_id) . '&lines=' . $lines . '" style="font-size: 12px;">View Details</a></td>';
                        echo '</tr>';
                    }
                }
            }
            echo '</table>';
            echo '</div>';
        }
        
        // Show specific campaign details if requested
        if (isset($_GET['campaign'])) {
            $campaign_id = $_GET['campaign'];
            $progress_file = CAMPAIGN_DATA_DIR . "/campaign_{$campaign_id}_progress.json";
            if (file_exists($progress_file)) {
                $content = file_get_contents($progress_file);
                $data = json_decode($content, true);
                if ($data) {
                    echo '<div class="log-section">';
                    echo '<h3>📋 Campaign Details: ' . htmlspecialchars($campaign_id) . '</h3>';
                    echo '<div class="log-content">' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</div>';
                    echo '</div>';
                }
            }
        }
        ?>
    </div>
    
    <script>
        function clearLogs() {
            if (confirm('Are you sure you want to clear all logs? This cannot be undone.')) {
                fetch('clear_logs.php', {method: 'POST'})
                    .then(response => response.text())
                    .then(data => {
                        alert('Logs cleared');
                        location.reload();
                    })
                    .catch(error => {
                        alert('Error clearing logs: ' + error);
                    });
            }
        }
        
        // Highlight error lines
        document.addEventListener('DOMContentLoaded', function() {
            const logContents = document.querySelectorAll('.log-content');
            logContents.forEach(function(logContent) {
                const lines = logContent.innerHTML.split('\n');
                const highlightedLines = lines.map(function(line) {
                    if (line.includes('[ERROR]') || line.includes('Failed') || line.includes('Error:')) {
                        return '<span class="error-line">' + line + '</span>';
                    } else if (line.includes('[WARNING]') || line.includes('Warning:')) {
                        return '<span class="warning-line">' + line + '</span>';
                    } else if (line.includes('successful') || line.includes('completed') || line.includes('sent')) {
                        return '<span class="success-line">' + line + '</span>';
                    }
                    return line;
                });
                logContent.innerHTML = highlightedLines.join('\n');
            });
        });
    </script>
</body>
</html>
