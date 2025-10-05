<?php

class Logger
{
    private $logFile;
    private $sensitivePatterns;

    public function __construct($logFile = 'app.log')
    {
        $this->logFile = $logFile;
        $this->sensitivePatterns = [
            '/password["\s]*[:=]["\s]*[^\s,}]*/i',
            '/pwd["\s]*[:=]["\s]*[^\s,}]*/i',
            '/pass["\s]*[:=]["\s]*[^\s,}]*/i',
            '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/',
            '/\b\d{13,19}\b/',
            '/\b\d{3}-\d{2}-\d{4}\b/',
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            '/token["\s]*[:=]["\s]*[^\s,}]*/i',
            '/api_key["\s]*[:=]["\s]*[^\s,}]*/i',
            '/secret["\s]*[:=]["\s]*[^\s,}]*/i',
            '/authorization["\s]*[:=]["\s]*[^\s,}]*/i'
        ];
    }

    public function debug($message, $context = [])
    {
        $this->log('DEBUG', $message, $context);
    }

    public function info($message, $context = [])
    {
        $this->log('INFO', $message, $context);
    }

    public function warning($message, $context = [])
    {
        $this->log('WARNING', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->log('ERROR', $message, $context);
    }

    private function log($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $source = isset($backtrace[2]) ? basename($backtrace[2]['file']) . ':' . $backtrace[2]['line'] : 'unknown';
        
        $sanitizedMessage = $this->sanitizeSensitiveData($message);
        $contextString = !empty($context) ? ' Context: ' . $this->sanitizeSensitiveData(json_encode($context)) : '';
        
        $logEntry = "[{$timestamp}] [{$level}] [{$source}]: {$sanitizedMessage}{$contextString}" . PHP_EOL;
        
        $this->writeToFile($logEntry);
    }

    private function sanitizeSensitiveData($data)
    {
        $sanitized = $data;
        foreach ($this->sensitivePatterns as $pattern) {
            $sanitized = preg_replace_callback($pattern, function($matches) {
                $match = $matches[0];
                if (strpos($match, ':') !== false) {
                    $parts = explode(':', $match, 2);
                    return $parts[0] . ': [REDACTED]';
                } elseif (strpos($match, '=') !== false) {
                    $parts = explode('=', $match, 2);
                    return $parts[0] . '= [REDACTED]';
                } else {
                    return '[REDACTED]';
                }
            }, $sanitized);
        }
        return $sanitized;
    }

    private function writeToFile($logEntry)
    {
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}


<?php

require_once 'Logger.php';

$logger = new Logger();

$logger->debug('This is a debug message for troubleshooting');

$logger->info('User login successful', ['user_id' => 12345, 'ip' => '192.168.1.100']);

$logger->warning('Memory usage is high', ['memory_used' => '85%', 'threshold' => '80%']);

$logger->error('Database connection failed', ['host' => 'localhost', 'port' => 3306]);

$logger->info('Processing payment with sensitive data', [
    'user_id' => 12345,
    'password' => 'secret123',
    'credit_card' => '4532-1234-5678-9012',
    'email' => 'user@example.com'
]);

$logger->warning('Failed login attempt with password: mypassword123 for user@domain.com');

$logger->error('API authentication failed with token: abc123xyz456 and api_key: secret_key_here');
?>