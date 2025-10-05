<?php

// Logger.php

class Logger {
    private string $logFile;
    private const DEBUG   = 'DEBUG';
    private const INFO    = 'INFO';
    private const WARNING = 'WARNING';
    private const ERROR   = 'ERROR';

    private const SENSITIVE_PATTERNS = [
        ['pattern' => '/(?i)(?:pass(?:word)?|pwd)\s*[:=]\s*([^\s,]+)/', 'type' => 'group_1'],
        ['pattern' => '/(?i)(?:api_key|token|access_token|secret|auth_token)\s*[:=]\s*([^\s,]+)/', 'type' => 'group_1'],
        ['pattern' => '/(?i)(authorization:\s*bearer)\s+([a-z0-9._-]+)/', 'type' => 'group_2_with_prefix'],
        ['pattern' => '/\b(?:\d[ -]*?){13,16}\b/', 'type' => 'full_match'],
        ['pattern' => '/\b\d{3}[- ]?\d{2}[- ]?\d{4}\b/', 'type' => 'full_match'],
        ['pattern' => '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', 'type' => 'full_match'],
        ['pattern' => '/(?i)(?:"ssn"|"p_?id"|"drivers_license"|"licence_number")\s*:\s*"([^"]+)"/', 'type' => 'group_1'],
        ['pattern' => '/(?i)(ssn|p_?id|drivers_license|licence_number)=([^&\s]+)/', 'type' => 'group_2'],
    ];

    public function __construct(string $logFilePath = 'app.log') {
        $this->logFile = $logFilePath;
    }

    public function debug(string $message): void {
        $this->log(self::DEBUG, $message);
    }

    public function info(string $message): void {
        $this->log(self::INFO, $message);
    }

    public function warning(string $message): void {
        $this->log(self::WARNING, $message);
    }

    public function error(string $message): void {
        $this->log(self::ERROR, $message);
    }

    private function log(string $level, string $message): void {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $file = $trace[1]['file'] ?? 'unknown_file';
        $line = $trace[1]['line'] ?? 0;

        $sanitizedMessage = $this->sanitizeMessage($message);
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] [%s]: [%s:%d] %s" . PHP_EOL,
            $timestamp,
            $level,
            basename($file),
            $line,
            $sanitizedMessage
        );

        $this->writeLog($logEntry);
    }

    private function sanitizeMessage(string $message): string {
        $sanitized = $message;

        foreach (self::SENSITIVE_PATTERNS as $rule) {
            $pattern = $rule['pattern'];
            $type = $rule['type'];

            switch ($type) {
                case 'full_match':
                    $sanitized = preg_replace($pattern, '[REDACTED]', $sanitized);
                    break;
                case 'group_1':
                    $sanitized = preg_replace_callback($pattern, function($matches) {
                        return str_replace($matches[1], '[REDACTED]', $matches[0]);
                    }, $sanitized);
                    break;
                case 'group_2':
                     $sanitized = preg_replace_callback($pattern, function($matches) {
                        return str_replace($matches[2], '[REDACTED]', $matches[0]);
                    }, $sanitized);
                    break;
                case 'group_2_with_prefix':
                    $sanitized = preg_replace_callback($pattern, function($matches) {
                        return $matches[1] . ' [REDACTED]';
                    }, $sanitized);
                    break;
                default:
                    $sanitized = preg_replace($pattern, '[REDACTED]', $sanitized);
                    break;
            }
        }
        return $sanitized;
    }

    private function writeLog(string $logEntry): void {
        $fileHandle = fopen($this->logFile, 'a');
        if ($fileHandle === false) {
            error_log("ERROR: Could not open log file: " . $this->logFile, 0);
            return;
        }

        if (flock($fileHandle, LOCK_EX)) {
            fwrite($fileHandle, $logEntry);
            fflush($fileHandle);
            flock($fileHandle, LOCK_UN);
        } else {
            error_log("WARNING: Could not acquire lock on log file: " . $this->logFile, 0);
        }

        fclose($fileHandle);
    }
}

?>
<?php

// app.php

require_once __DIR__ . '/Logger.php';

$logger = new Logger();

$logger->debug('This is a debug message. User agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
$logger->info('User logged in successfully from IP 192.168.1.100. Username: test_user');
$logger->warning('Failed login attempt for user admin from IP 203.0.113.4. Password: "my_weak_password"');
$logger->error('Database connection failed. Details: SQLSTATE[HY000]: An error occurred.');

$logger->info('Processing payment for order #12345. Credit card: 4111-2222-3333-4444, CVV: 123.');
$logger->debug('User registration. Email: john.doe@example.com, SSN: 123-45-6789. Password: MySuperSecretPassword123');
$logger->info('API call successful. Endpoint: /api/v1/data. API Key: abcdef123456. Authorization: Bearer gfedcba654321.');
$logger->warning('Suspicious activity detected. Account: someuser, IP: 1.2.3.4. Session_token: xyz123abc456.');
$logger->info('User profile update. PII: {"first_name":"John", "last_name":"Doe", "ssn":"987-65-4321", "credit_card":"5555 4444 3333 2222", "drivers_license":"ABC12345"}');
$logger->error('Critical error: Unhandled exception. Details: "password":"some_db_pass", "salt":"some_salt".');

echo "Log messages written to app.log\n";

?>