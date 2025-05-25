<?php
// index1.php - Email Campaign Manager
// Version: 2025-01-25-Fixed-Session-Directory
ob_start('ob_gzhandler', 4096);

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';

$version = '2025-01-25-Fixed-Session-Directory';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', PHP_ERRORS_LOG);

// Get the actual session path (with fallback support)
$session_path = get_session_path();

// Session setup with better error handling
if (!ensure_directory($session_path, 0700)) {
    // Try one more fallback - use PHP's default session path
    $session_path = session_save_path();
    if (empty($session_path)) {
        $session_path = sys_get_temp_dir();
    }
    log_message("Using fallback session path: $session_path");
}

// Verify the session path is writable
if (!is_writable($session_path)) {
    log_message("Session path not writable: $session_path, trying to fix permissions");
    @chmod($session_path, 0700);
    
    if (!is_writable($session_path)) {
        // Last resort - use system temp directory
        $session_path = sys_get_temp_dir();
        log_message("Using system temp directory for sessions: $session_path");
    }
}

// Configure session with the working path
ini_set('session.save_path', $session_path);
ini_set('session.name', 'PHPSESSID');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

// Start session with error handling
$session_started = false;
$session_error = '';

try {
    if (session_start()) {
        $session_started = true;
        log_message("Session started successfully with path: $session_path");
    } else {
        $session_error = 'session_start() returned false';
    }
} catch (Exception $e) {
    $session_error = $e->getMessage();
}

if (!$session_started) {
    $error = error_get_last();
    $error_msg = $error ? $error['message'] : $session_error;
    log_message("Failed to start session. Error: {$error_msg}");
    
    // Try to start session without custom path
    ini_restore('session.save_path');
    if (!session_start()) {
        ob_end_clean();
        die('Session initialization failed completely. Please check server configuration.');
    }
    log_message("Session started with default path as fallback");
}

$csrf_token = bin2hex(random_bytes(32));
$_SESSION['model_b_csrf_token'] = $csrf_token;
log_message("CSRF token generated in index1.php: {$csrf_token}");

// Get relative path for CSS
$css_relative_path = getRelativeURL(CAMPAIGN_AUTO_PATH . '/styles.css');

if (ob_get_length() > 0) {
    $unexpected_output = ob_get_contents();
    log_message("Unexpected output detected before HTML: " . $unexpected_output);
    ob_clean();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Campaign Manager</title>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        select {
            height: 42px;
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        #content-editor {
            height: 200px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .button-group {
            margin: 20px 0;
        }
        button {
            padding: 12px 24px;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            font-size: 14px;
            font-weight: bold;
        }
        button:disabled {
            background: #ccc !important;
            cursor: not-allowed !important;
        }
        .btn-start { background: #28a745; }
        .btn-pause { background: #ffc107; color: #000; }
        .btn-resume { background: #17a2b8; }
        .btn-stop { background: #dc3545; }
        .progress {
            width: 100%;
            height: 25px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-bar {
            height: 100%;
            background-color: #28a745;
            width: 0%;
            transition: width 0.3s ease-in-out;
            text-align: center;
            color: white;
            line-height: 25px;
            font-size: 12px;
            font-weight: bold;
        }
        #progress-list {
            list-style: none;
            padding: 0;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f8f9fa;
        }
        #progress-list li {
            padding: 8px 12px;
            border-bottom: 1px solid #dee2e6;
            font-size: 13px;
        }
        #progress-list li:last-child {
            border-bottom: none;
        }
        .status-running { color: #28a745; }
        .status-paused { color: #ffc107; }
        .status-stopped { color: #dc3545; }
        .status-completed { color: #6f42c1; }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row .form-group {
            flex: 1;
        }
        h2 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        .path-info {
            background: #e9ecef;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #666;
        }
        .path-info .status {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
            margin-left: 5px;
        }
        .status-ok { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>📧 Email Campaign Manager</h2>
        
        <?php if ($session_started): ?>
            <div class="alert alert-success">
                ✅ System initialized successfully! Session path: <?php echo htmlspecialchars($session_path); ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                ⚠️ Session started with fallback configuration. Some features may be limited.
            </div>
        <?php endif; ?>
        
        <div class="path-info">
            <strong>📁 System Paths & Status:</strong><br>
            Campaign Data: <?php echo htmlspecialchars(CAMPAIGN_DATA_DIR); ?>
            <span class="status <?php echo is_writable(CAMPAIGN_DATA_DIR) ? 'status-ok' : 'status-error'; ?>">
                <?php echo is_writable(CAMPAIGN_DATA_DIR) ? 'WRITABLE' : 'NOT WRITABLE'; ?>
            </span><br>
            
            Sessions: <?php echo htmlspecialchars($session_path); ?>
            <span class="status <?php echo is_writable($session_path) ? 'status-ok' : 'status-error'; ?>">
                <?php echo is_writable($session_path) ? 'WRITABLE' : 'NOT WRITABLE'; ?>
            </span><br>
            
            Logs: <?php echo htmlspecialchars(LOG_PATH); ?>
            <span class="status <?php echo is_writable(LOG_PATH) ? 'status-ok' : 'status-error'; ?>">
                <?php echo is_writable(LOG_PATH) ? 'WRITABLE' : 'NOT WRITABLE'; ?>
            </span><br>
            
            PHP Version: <?php echo PHP_VERSION; ?> | 
            Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
        </div>
        
        <form id="campaign-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="sender_email">Sender Email:</label>
                    <select id="sender_email" name="sender_email" required>
                        <option value="">Select sender email...</option>
                        <?php foreach ($emailConfigs as $email => $config): ?>
                            <option value="<?php echo htmlspecialchars($email); ?>">
                                <?php echo htmlspecialchars($config['sender_name'] . ' <' . $email . '>'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="interval">Interval (seconds):</label>
                    <input type="number" id="interval" name="interval" value="5" min="1" max="3600" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="subject">Subject:</label>
                    <input type="text" id="subject" name="subject" value="Important Update" required>
                </div>
                <div class="form-group">
                    <label for="max_emails">Max Emails Per Campaign:</label>
                    <input type="number" id="max_emails" name="max_emails" value="100" min="1" required>
                </div>
            </div>

            <div class="form-group">
                <label for="recipients">Recipients (email,first_name per line):</label>
                <textarea id="recipients" name="recipients" rows="6" placeholder="example1@email.com,John&#10;example2@email.com,Jane&#10;example3@email.com,Bob" required></textarea>
            </div>

            <div class="form-group">
                <label for="content-editor">Email Content:</label>
                <div id="content-editor"></div>
                <input type="hidden" id="content" name="content">
            </div>

            <div class="button-group">
                <button type="submit" id="start-btn" class="btn-start">🚀 Start Campaign</button>
                <button type="button" id="pause-btn" class="btn-pause" disabled>⏸️ Pause</button>
                <button type="button" id="resume-btn" class="btn-resume" disabled>▶️ Resume</button>
                <button type="button" id="stop-btn" class="btn-stop" disabled>⏹️ Stop</button>
            </div>
        </form>

        <div class="progress" id="progress-container">
            <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
        </div>

        <ul id="progress-list"></ul>
    </div>

    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script>
        console.log('Email Campaign Manager version:', '<?php echo $version; ?>');
        console.log('Session started:', <?php echo $session_started ? 'true' : 'false'; ?>);

        document.addEventListener('DOMContentLoaded', function () {
            let quill = null;
            const defaultContent = '<p>Dear {FirstName},</p><p><br></p><p>We hope this email finds you well.</p><p><br></p><p>Best regards,</p><p>{SenderName}</p>';
            
            try {
                quill = new Quill('#content-editor', {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline'],
                            ['link'],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'color': [] }, { 'background': [] }],
                            ['clean']
                        ]
                    }
                });
                quill.root.innerHTML = defaultContent;
                console.log('Quill editor initialized successfully');
            } catch (e) {
                console.error('Quill initialization failed:', e);
                document.getElementById('content').value = defaultContent;
            }

            const form = document.getElementById('campaign-form');
            const startBtn = document.getElementById('start-btn');
            const pauseBtn = document.getElementById('pause-btn');
            const resumeBtn = document.getElementById('resume-btn');
            const stopBtn = document.getElementById('stop-btn');
            const progressBar = document.querySelector('.progress-bar');
            const progressList = document.getElementById('progress-list');
            
            let campaignId = null;
            let isPolling = false;

            function updateProgressList(messages) {
                if (!Array.isArray(messages)) return;
                
                progressList.innerHTML = '';
                messages.forEach((message, index) => {
                    const li = document.createElement('li');
                    li.textContent = message;
                    
                    // Add status classes for styling
                    if (message.includes('started') || message.includes('resumed')) {
                        li.className = 'status-running';
                    } else if (message.includes('paused')) {
                        li.className = 'status-paused';
                    } else if (message.includes('stopped')) {
                        li.className = 'status-stopped';
                    } else if (message.includes('finished') || message.includes('completed')) {
                        li.className = 'status-completed';
                    }
                    
                    progressList.appendChild(li);
                });
                
                // Auto-scroll to bottom
                progressList.scrollTop = progressList.scrollHeight;
            }

            function updateProgressBar(sent, total) {
                const percentage = total > 0 ? (sent / total) * 100 : 0;
                progressBar.style.width = `${percentage}%`;
                progressBar.setAttribute('aria-valuenow', percentage);
                progressBar.textContent = `${Math.round(percentage)}% (${sent}/${total})`;
            }

            function updateButtonStates(status) {
                console.log('Updating button states for status:', status);
                
                // Reset all buttons
                [startBtn, pauseBtn, resumeBtn, stopBtn].forEach(btn => btn.disabled = true);
                
                switch (status) {
                    case 'running':
                    case 'pending':
                        pauseBtn.disabled = false;
                        stopBtn.disabled = false;
                        break;
                    case 'paused':
                        resumeBtn.disabled = false;
                        stopBtn.disabled = false;
                        break;
                    case 'completed':
                    case 'stopped':
                    case 'error':
                    default:
                        startBtn.disabled = false;
                        break;
                }
            }

            async function pollProgress() {
                if (!campaignId || isPolling) return;
                
                isPolling = true;
                try {
                    const response = await fetch(`get_progress.php?campaign_id=${encodeURIComponent(campaignId)}`, {
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.error) {
                        updateProgressList([`Error: ${data.error}`]);
                        updateButtonStates('error');
                        return;
                    }
                    
                    if (data.messages) {
                        updateProgressList(data.messages);
                    }
                    
                    updateProgressBar(data.emails_sent || 0, data.total_emails || 0);
                    updateButtonStates(data.status);
                    
                    // Continue polling if campaign is active
                    if (['running', 'pending', 'paused'].includes(data.status)) {
                        setTimeout(pollProgress, 2000);
                    }
                    
                } catch (e) {
                    console.error('Poll error:', e);
                    updateProgressList([`Poll error: ${e.message}`]);
                } finally {
                    isPolling = false;
                }
            }

            async function startCampaign() {
                updateButtonStates('running');
                document.getElementById('content').value = quill ? quill.root.innerHTML : defaultContent;
                
                progressList.innerHTML = '';
                progressBar.style.width = '0%';
                progressBar.textContent = '0%';

                const formData = new FormData(form);
                
                try {
                    const response = await fetch('start_campaign.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        campaignId = result.campaign_id;
                        updateProgressList([`Campaign started: ${campaignId}`]);
                        setTimeout(pollProgress, 1000);
                    } else {
                        updateProgressList([`Error: ${result.error}`]);
                        updateButtonStates('error');
                    }
                } catch (e) {
                    updateProgressList([`Error: ${e.message}`]);
                    updateButtonStates('error');
                }
            }

            async function pauseCampaign() {
                if (!campaignId) return;
                
                try {
                    const response = await fetch(`pause_campaign.php?campaign_id=${encodeURIComponent(campaignId)}`, {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        updateButtonStates('paused');
                    } else {
                        updateProgressList([`Error pausing: ${result.error}`]);
                    }
                } catch (e) {
                    updateProgressList([`Pause error: ${e.message}`]);
                }
            }

            async function resumeCampaign() {
                if (!campaignId) return;
                
                try {
                    const response = await fetch(`resume_campaign.php?campaign_id=${encodeURIComponent(campaignId)}`, {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        updateButtonStates('running');
                        setTimeout(pollProgress, 1000);
                    } else {
                        updateProgressList([`Error resuming: ${result.error}`]);
                    }
                } catch (e) {
                    updateProgressList([`Resume error: ${e.message}`]);
                }
            }

            async function stopCampaign() {
                if (!campaignId) return;
                
                if (!confirm(`Are you sure you want to stop campaign ${campaignId}? This cannot be undone.`)) {
                    return;
                }
                
                try {
                    const response = await fetch(`stop_campaign.php?campaign_id=${encodeURIComponent(campaignId)}`, {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        updateButtonStates('stopped');
                    } else {
                        updateProgressList([`Error stopping: ${result.error}`]);
                    }
                } catch (e) {
                    updateProgressList([`Stop error: ${e.message}`]);
                }
            }

            // Event listeners
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                startCampaign();
            });

            pauseBtn.addEventListener('click', pauseCampaign);
            resumeBtn.addEventListener('click', resumeCampaign);
            stopBtn.addEventListener('click', stopCampaign);

            // Initialize button states
            updateButtonStates('idle');
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
