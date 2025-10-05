<?php

class Logger
{
    private string $logFilePath;

    // Regular expressions for detecting and redacting sensitive information.
    // These patterns aim to be reasonably robust without being overly aggressive
    // and causing false positives on common numbers or words.
    private const SENSITIVE_PATTERNS = [
        // Generic password patterns (e.g., password=xyz, "password":"xyz", password:"xyz")
        // This pattern identifies the key and captures the value.
        '/\b(?:password|pass|pwd|secret|token|apikey|api_key|auth_token|bearer)\s*[:=]\s*[\'"]?([^\s\'"&,]+)[\'"]?/i',
        // Credit Card Numbers (Visa, MasterCard, Amex, Discover - 13-16 digits with common prefixes)
        '/\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9]{2})[0-9]{12}|3[47][0-9]{13})\b/',
        // Social Security Numbers (US format: XXX-XX-XXXX or XXXXXXXXX)
        '/\b\d{3}[- ]?\d{2}[- ]?\d{4}\b/',
        // Email addresses
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/',
        // Phone numbers (various formats, US-centric)
        '/\b(?:\+?\d{1,3}[\s-]?)?\(?\d{3}\)?[\s-]?\d{3}[\s-]?\d{4}\b/',
        // Bank account numbers (simple pattern, potentially broad, often alongside keywords)
        // Similar to password patterns, captures the value after a keyword.
        '/\b(?:account_number|bank_account|routing_number|pin)\s*[:=]\s*[\'"]?(\d{8,17})[\'"]?/i',
        // Generic 3-digit CVV/CVC
        '/\b(?:cvv|cvc|security_code)\s*[:=]\s*[\'"]?(\d{3,4})[\'"]?/i',
    ];

    public function __construct(string $logFileName = 'app.log')
    {
        $this->logFilePath = __DIR__ . '/' . $logFileName;
    }

    public function debug(string $message): void
    {
        $this->writeLog('DEBUG', $message);
    }

    public function info(string $message): void
    {
        $this->writeLog('INFO', $message);
    }

    public function warning(string $message): void
    {
        $this->writeLog('WARNING', $message);
    }

    public function error(string $message): void
    {
        $this->writeLog('ERROR', $message);
    }

    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');

        // Get caller info to include as "source of the event" in the message.
        $caller = $this->getCallerInfo();
        $source = sprintf("%s:%d", $caller['file'], $caller['line']);

        // Apply sensitive data filtering to the message.
        $filteredMessage = $this->redactSensitiveInformation($message);

        // Construct the log entry following the specified format:
        // [YYYY-MM-DD HH:MM:SS] [LEVEL]: source - message
        // The source is prepended to the message part.
        $logEntry = sprintf("[%s] [%s]: %s - %s\n", $timestamp, $level, $source, $filteredMessage);

        // Attempt to append to the log file.
        // Use FILE_APPEND to add content to the end of the file.
        // Use LOCK_EX to prevent anyone else from writing to the file at the same time.
        if (file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            // In a production environment, you might log this failure to
            // stderr, an emergency log, or throw a more specific exception.
            // For this exercise, we fail silently to avoid crashing the
            // application due to logging issues.
        }
    }

    private function redactSensitiveInformation(string $message): string
    {
        $redactedMessage = $message;
        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            // Check if the pattern contains a capturing group for values (e.g., key=value patterns).
            // This is identified by the presence of a '(?P<key>' or simply '('.
            if (preg_match('/(\([^)]+\))/', $pattern)) { // Simple check for a capturing group
                $redactedMessage = preg_replace_callback($pattern, function($matches) {
                    // $matches[0] is the full matched string (e.g., "password=mysecret")
                    // $matches[1] is the captured value (e.g., "mysecret")
                    if (isset($matches[1])) {
                        // Replace the captured sensitive value with [REDACTED] within the full match.
                        return str_replace($matches[1], '[REDACTED]', $matches[0]);
                    }
                    return $matches[0]; // Should not happen if pattern is designed for groups.
                }, $redactedMessage);
            } else {
                // For other patterns (e.g., standalone CC, SSN, Email, Phone), redact the whole match.
                $redactedMessage = preg_replace($pattern, '[REDACTED]', $redactedMessage);
            }
        }
        return $redactedMessage;
    }

    private function getCallerInfo(): array
    {
        // Get the call stack (backtrace). DEBUG_BACKTRACE_IGNORE_ARGS makes it more performant.
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $file = 'unknown_file';
        $line = 0;

        // The call stack for a log event typically looks like this:
        // [0] Logger->getCallerInfo() in Logger.php
        // [1] Logger->writeLog() in Logger.php
        // [2] Logger->debug()/info()/warning()/error() in Logger.php
        // [3] The actual caller (e.g., app.php, or another class/method)
        // We want the information from frame [3].
        if (isset($backtrace[3]) && isset($backtrace[3]['file'])) {
            $file = basename($backtrace[3]['file']);
            $line = $backtrace[3]['line'] ?? 0;
        } elseif (isset($backtrace[2]) && isset($backtrace[2]['file'])) {
            // Fallback for slightly shallower call stacks, e.g., if a log method is called directly
            // from the global scope of a script, not via an intermediary method.
            $file = basename($backtrace[2]['file']);
            $line = $backtrace[2]['line'] ?? 0;
        }

        return ['file' => $file, 'line' => $line];
    }
}

<?php

require_once 'Logger.php';

$logger = new Logger();

// --- Demonstrating different log levels ---

// DEBUG: Detailed information, typically only of interest to developers during debugging.
$logger->debug('User "john_doe" requested inventory list from IP 192.168.1.5.');

// INFO: General system events, successful operations, and high-level activity.
$logger->info('Inventory item "Laptop Pro" (ID: 1001) quantity updated to 50.');
$logger->info('A new user "jane_smith" registered successfully via signup form.');

// WARNING: Potential issues that don't prevent execution but might require attention.
$logger->warning('Low stock alert: Item "Wireless Mouse" (ID: 2005) has only 5 units remaining.');
$logger->warning('Failed attempt to access an admin resource by user "guest" from IP 10.0.0.10.');

// ERROR: Critical problems that prevent normal operation or lead to data loss.
$logger->error('Database connection failed. Please check credentials and server status.');
$logger->error('File upload failed for product image "IMG_001.jpg" due to insufficient disk space.');

// --- Demonstrating sensitive data filtering ---

$logger->info('User "admin" attempted login with password "Sup3rS3cr3tP@ssw0rd".');
$logger->debug('Processing user profile update for email: test.user@example.com, phone: +1 (555) 123-4567, SSN: 987-65-4321.');
$logger->warning('An unauthorized request contained API_KEY: abcdef1234567890 and client_secret: mytopsecretkey.');
$logger->error('Transaction failed for order #789. Credit card number 4111-2222-3333-4444 and CVV 123 was declined.');
$logger->info('New payment method added: Bank Account No: 123456789012345 and PIN 4321.');
$logger->debug('Received data containing a potential credit card number: 5123-4567-8901-2345 and a phone number (555)867-5309.');

echo "Log messages have been written to app.log\n";
?>