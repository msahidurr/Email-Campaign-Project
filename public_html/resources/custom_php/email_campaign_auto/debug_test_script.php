<?php
// debug_test_script.php - Test script execution
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/private_html/custom_php/config.php';

header('Content-Type: text/plain');

$php_executable = 'php';
$php_test = shell_exec("which php 2>/dev/null");
if (empty($php_test)) {
    $php_paths = ['/usr/bin/php', '/usr/local/bin/php', '/opt/php/bin/php'];
    foreach ($php_paths as $path) {
        if (file_exists($path) && is_executable($path)) {
            $php_executable = $path;
            break;
        }
    }
}

echo "Testing PHP executable: {$php_executable}\n";
echo "Send emails script: " . SEND_EMAILS_SCRIPT . "\n";
echo "Script exists: " . (file_exists(SEND_EMAILS_SCRIPT) ? 'YES' : 'NO') . "\n";
echo "Script readable: " . (is_readable(SEND_EMAILS_SCRIPT) ? 'YES' : 'NO') . "\n\n";

$test_command = "{$php_executable} " . escapeshellarg(SEND_EMAILS_SCRIPT) . " test123456 2>&1";
echo "Test command: {$test_command}\n\n";

echo "Executing test command...\n";
echo str_repeat('-', 50) . "\n";

$output = shell_exec($test_command);
echo $output;

echo "\n" . str_repeat('-', 50) . "\n";
echo "Test completed.\n";
?>
