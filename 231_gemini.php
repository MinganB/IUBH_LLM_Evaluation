<?php

class Logger
{
    private string $logFilePath;
    private array $sensitivePatterns;

    public const LEVEL_DEBUG    = 'DEBUG';
    public const LEVEL_INFO     = 'INFO';
    public const LEVEL_WARNING  = 'WARNING';
    public const LEVEL_ERROR    = 'ERROR';
    public const LEVEL_CRITICAL = 'CRITICAL';

    public function __construct(string $logFilePath)
    {
        $this->logFilePath = $logFilePath;
        $this->sensitivePatterns = [
            '/(password|pwd|pass|secret|api_key|token|auth_token|bearer)\s*[:=]\s*\'?"?[\w\d.!@#$%^&*()_+\-=\[\]{}|;:,.<>?]+\'?"?/i',
            '/\b\d{4}[- ]?\d{4}[- ]?\d{4}[- ]?\d{4}\b/',
            '/\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9]{2})[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})\b/',
            '/\b(?:\d{3}[- ]?\d{2}[- ]?\d{4}|\d{9})\b/',
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        ];

        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
    }

    public function debug(string $message): void
    {
        $this->writeLog(self::LEVEL_DEBUG, $message);
    }

    public function info(string $message): void
    {
        $this->writeLog(self::LEVEL_INFO, $message);
    }

    public function warning(string $message): void
    {
        $this->writeLog(self::LEVEL_WARNING, $message);
    }

    public function error(string $message): void
    {
        $this->writeLog(self::LEVEL_ERROR, $message);
    }

    public function critical(string $message): void
    {
        $this->writeLog(self::LEVEL_CRITICAL, $message);
    }

    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $sanitizedMessage = $this->sanitizeMessage($message);
        $caller = $this->getCallerInfo();

        $logEntry = sprintf(
            '[%s] [%s] [%s:%d] %s%s',
            $timestamp,
            $level,
            $caller['file'],
            $caller['line'],
            $sanitizedMessage,
            PHP_EOL
        );

        file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function sanitizeMessage(string $message): string
    {
        foreach ($this->sensitivePatterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }
        return $message;
    }

    private function getCallerInfo(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);

        if (isset($trace[3]['file']) && isset($trace[3]['line'])) {
            return [
                'file' => basename($trace[3]['file']),
                'line' => $trace[3]['line']
            ];
        }
        
        return ['file' => 'unknown', 'line' => 0];
    }
}
<?php

require_once 'Logger.php';

$logFile = __DIR__ . '/logs/inventory_app.log';
$logger = new Logger($logFile);

$logger->debug('User "john.doe" accessed product list page.');

$logger->info('Product "SKU12345" added to inventory by user "admin".');
$logger->info('Inventory report generated successfully for October.');

$logger->warning('Low stock alert: Product "SKU67890" quantity is now below threshold (5 units).');
$logger->warning('Failed attempt to connect to external supplier API, retrying...');

$logger->error('Failed to update product "SKU12345": Database write error.');
$logger->error('User "jane.smith" tried to access an unauthorized resource: /admin/users');

$logger->critical('Database connection lost. All inventory operations are halted.');
$logger->critical('Critical error: Payment gateway service is down. Transactions failing.');

$logger->info('User login attempt for "testuser" with password:"mySuperSecretPassword".');
$logger->debug('New user registration with email:test@example.com and password:AnotherSecurePass123.');

$logger->info('Processing payment for order #12345 with credit_card:1234-5678-9012-3456.');
$logger->debug('Customer "Alice" updated payment info. CC details: 4111222233334444.');

$logger->info('New employee registered with SSN:987-65-4321.');

$logger->debug('Sending notification email to user.email@domain.com.');
$logger->warning('Failed to send email to bad.email@invalid-domain.xyz.');

echo "Log messages generated. Check the file: " . realpath($logFile) . PHP_EOL;
?>