<?php
// Email Headers Comparison - PHP App vs Email Client
// Version: 2025-01-25-TheFastMode-Zoho-Headers

class EmailHeaderManager {
    private $domain;
    private $serverIP;
    
    public function __construct($domain = null) {
        $this->domain = $domain ?: $this->detectDomain();
        $this->serverIP = $this->getServerIP();
    }
    
    private function detectDomain() {
        if (isset($_SERVER['HTTP_HOST'])) {
            return $_SERVER['HTTP_HOST'];
        }
        if (isset($_SERVER['SERVER_NAME'])) {
            return $_SERVER['SERVER_NAME'];
        }
        return 'localhost';
    }
    
    private function getServerIP() {
        return $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
    }
    
    /**
     * Generate a proper Message-ID like email clients do
     */
    public function generateMessageID($sender_email = null) {
        $domain = $sender_email ? substr(strrchr($sender_email, '@'), 1) : $this->domain;
        $unique_part = bin2hex(random_bytes(16));
        $timestamp = time();
        return "<{$unique_part}.{$timestamp}@{$domain}>";
    }
    
    /**
     * Get domain-specific email client configuration
     */
    private function getDomainSpecificConfig($sender_email) {
        $domain = substr(strrchr($sender_email, '@'), 1);
        
        // Hardcoded configurations for specific domains
        switch (strtolower($domain)) {
            case 'thefastmode.com':
                return [
                    'client_type' => 'zoho',
                    'x_mailer' => 'Zoho Mail',
                    'user_agent' => 'Zoho Mail',
                    'priority' => '3',
                    'importance' => 'Normal',
                    'additional_headers' => [
                        'X-Zoho-Virus-Status' => 'Clean',
                        'X-Zoho-Spam-Status' => 'No',
                        'X-ZohoMail-DKIM' => 'pass'
                    ]
                ];
                
            case 'gmail.com':
                return [
                    'client_type' => 'gmail',
                    'x_mailer' => 'Gmail',
                    'user_agent' => 'Gmail Web Client',
                    'priority' => '3',
                    'importance' => 'Normal',
                    'additional_headers' => [
                        'X-Google-Original-From' => $sender_email
                    ]
                ];
                
            default:
                return [
                    'client_type' => 'outlook',
                    'x_mailer' => 'Microsoft Outlook 16.0',
                    'user_agent' => 'Microsoft Outlook 16.0',
                    'priority' => '3',
                    'importance' => 'Normal',
                    'additional_headers' => []
                ];
        }
    }
    
    /**
     * Generate email client-like headers with domain-specific customization
     */
    public function getEmailClientHeaders($sender_email, $sender_name, $client_type = 'auto') {
        $headers = [];
        
        // Get domain-specific configuration
        $domain_config = $this->getDomainSpecificConfig($sender_email);
        
        // Use domain-specific client type if auto-detection is enabled
        if ($client_type === 'auto') {
            $client_type = $domain_config['client_type'];
        }
        
        // Message-ID (crucial for email threading and identification)
        $headers['Message-ID'] = $this->generateMessageID($sender_email);
        
        // Date in proper RFC format
        $headers['Date'] = date('r'); // RFC 2822 format
        
        // From header with proper formatting - hardcoded for thefastmode.com
        if (strpos(strtolower($sender_email), 'thefastmode.com') !== false) {
            // Hardcode specific format for thefastmode.com
            $headers['From'] = 'ANB <abn@thefastmode.com>';
        } else {
            $headers['From'] = $this->formatEmailAddress($sender_email, $sender_name);
        }
        
        // MIME headers
        $headers['MIME-Version'] = '1.0';
        $headers['Content-Type'] = 'text/html; charset=UTF-8';
        $headers['Content-Transfer-Encoding'] = 'quoted-printable';
        
        // Client-specific headers based on domain or manual selection
        switch (strtolower($client_type)) {
            case 'zoho':
                $headers['X-Mailer'] = 'Zoho Mail';
                // Note: PHPMailer's default X-Mailer is suppressed by setting $mail->XMailer = ' '
                $headers['User-Agent'] = 'Zoho Mail';
                $headers['X-Priority'] = '3';
                $headers['Importance'] = 'Normal';
                $headers['X-Zoho-Virus-Status'] = 'Clean';
                $headers['X-Zoho-Spam-Status'] = 'No';
                $headers['X-ZohoMail-DKIM'] = 'pass';
                $headers['X-Originating-IP'] = "[{$this->serverIP}]";
                break;
                
            case 'outlook':
                $headers['X-Mailer'] = 'Microsoft Outlook 16.0';
                // Note: PHPMailer's default X-Mailer is suppressed by setting $mail->XMailer = ' '
                $headers['X-Priority'] = '3';
                $headers['X-MSMail-Priority'] = 'Normal';
                $headers['Thread-Topic'] = 'Email Campaign';
                $headers['X-Originating-IP'] = "[{$this->serverIP}]";
                break;
                
            case 'thunderbird':
                $headers['User-Agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:91.0) Gecko/20100101 Thunderbird/91.0';
                $headers['X-Mozilla-Status'] = '0001';
                // Note: PHPMailer's default X-Mailer is suppressed by setting $mail->XMailer = ' '
                break;
                
            case 'apple_mail':
                $headers['X-Mailer'] = 'Apple Mail (2.3445.104.11)';
                // Note: PHPMailer's default X-Mailer is suppressed by setting $mail->XMailer = ' '
                $headers['X-Uniform-Type-Identifier'] = 'com.apple.mail-draft';
                break;
                
            case 'gmail':
                $headers['X-Google-Original-From'] = $this->formatEmailAddress($sender_email, $sender_name);
                $headers['X-Mailer'] = 'Gmail';
                // Note: PHPMailer's default X-Mailer is suppressed by setting $mail->XMailer = ' '
                break;
                
            default: // Generic professional client
                $headers['X-Mailer'] = $domain_config['x_mailer'];
                // Note: PHPMailer's default X-Mailer is suppressed by setting $mail->XMailer = ' '
                $headers['X-Priority'] = $domain_config['priority'];
                $headers['Importance'] = $domain_config['importance'];
        }
        
        // Add domain-specific additional headers
        foreach ($domain_config['additional_headers'] as $key => $value) {
            $headers[$key] = $value;
        }
        
        // Add proper boundary for multipart if needed
        if (strpos($headers['Content-Type'], 'multipart') !== false) {
            $boundary = 'boundary_' . bin2hex(random_bytes(8));
            $headers['Content-Type'] .= "; boundary=\"{$boundary}\"";
        }
        
        return $headers;
    }
    
    /**
     * Format email address properly
     */
    private function formatEmailAddress($email, $name) {
        if (empty($name)) {
            return $email;
        }
        
        // Encode name if it contains special characters
        if (preg_match('/[^\x20-\x7E]/', $name)) {
            $name = '=?UTF-8?B?' . base64_encode($name) . '?=';
        } elseif (preg_match('/[()<>@,;:\\".\[\]]/', $name)) {
            $name = '"' . str_replace('"', '\\"', $name) . '"';
        }
        
        return "{$name} <{$email}>";
    }
    
    /**
     * Convert headers array to string format for PHPMailer
     */
    public function formatHeadersForPHPMailer($headers) {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $formatted[] = "{$key}: {$value}";
        }
        return $formatted;
    }
    
    /**
     * Encode content as quoted-printable (like email clients do)
     */
    public function encodeQuotedPrintable($content) {
        return quoted_printable_encode($content);
    }
}

// Example usage and comparison
function demonstrateHeaderDifferences() {
    $headerManager = new EmailHeaderManager();
    
    echo "=== EMAIL CLIENT-STYLE HEADERS ===\n";
    $clientHeaders = $headerManager->getEmailClientHeaders('sender@example.com', 'John Doe', 'outlook');
    foreach ($clientHeaders as $key => $value) {
        echo "{$key}: {$value}\n";
    }
    
    echo "\n=== BASIC PHP HEADERS ===\n";
    echo "From: sender@example.com\n";
    echo "MIME-Version: 1.0\n";
    echo "Content-Type: text/html; charset=UTF-8\n";
    echo "X-Mailer: PHP/" . PHP_VERSION . "\n";
    
    echo "\n=== KEY IMPROVEMENTS ===\n";
    echo "1. Proper Message-ID generation\n";
    echo "2. Email client identification (X-Mailer)\n";
    echo "3. Quoted-printable encoding\n";
    echo "4. Proper date formatting\n";
    echo "5. Priority and threading headers\n";
    echo "6. Originating IP information\n";
}
?>
