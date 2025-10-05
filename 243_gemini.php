<?php

class Logger
{
    private string $logFile;
    private array $sensitiveValueRegexes = [];
    private array $sensitiveDataRegexes = [];

    public function __construct(string $logFilePath = 'app.log')
    {
        $this->logFile = $logFilePath;
        $this->initializeSensitiveRegexes();
    }

    private function initializeSensitiveRegexes(): void
    {
        $this->sensitiveValueRegexes[] = '/(\b(?:password|pwd|pass|api_key|secret|token)\s*[:=]\s*)([^\s,;]+)/i';

        $this->sensitiveDataRegexes[] = '/\b(?:\d{4}[ -]?){3,4}\d{1,4}\b(?!\d)/';
        $this->sensitiveDataRegexes[] = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/';
        $this->sensitiveDataRegexes[] = '/\b\d{3}[- ]?\d{2}[- ]?\d{4}\b/';
    }

    public function debug(string $message, string $source = 'App'): void
    {
        $this->writeLog('DEBUG', $message, $source);
    }

    public function info(string $message, string $source = 'App'): void
    {
        $this->writeLog('INFO', $message, $source);
    }

    public function warning(string $message, string $source = 'App'): void
    {
        $this->writeLog('WARNING', $message, $source);
    }

    public function error(string $message, string $source = 'App'): void
    {
        $this->writeLog('ERROR', $message, $source);
    }

    private function writeLog(string $level, string $message, string $source): void
    {
        $filteredMessage = $this->maskSensitiveData($message);
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] [%s] [%s]: %s\n", $timestamp, $level, $source, $filteredMessage);

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function maskSensitiveData(string $message): string
    {
        foreach ($this->sensitiveValueRegexes as $regex) {
            $message = preg_replace_callback(
                $regex,
                function ($matches) {
                    return $matches[1] . '[REDACTED]';
                },
                $message
            );
        }

        foreach ($this->sensitiveDataRegexes as $regex) {
            $message = preg_replace($regex, '[REDACTED]', $message);
        }

        return $message;
    }
}
<?php

require_once 'Logger.php';

$logger = new Logger();

$logger->info('User "john.doe" logged in from IP 192.168.1.100.', 'AuthHandler');
$logger->debug('User "jane.smith" attempted to access unauthorized resource /admin/users.', 'AccessControl');

$logger->info('Product "SKU001" stock updated from 50 to 45.', 'InventoryService');
$logger->warning('Low stock alert: Product "SKU005" quantity is 5. Reorder necessary.', 'StockMonitor');
$logger->error('Failed to update product "SKU003" data due to database connection error.', 'ProductRepository');

$sensitiveMessage1 = 'User registration failed for email@example.com with password=secret123. IP: 10.0.0.5';
$logger->error($sensitiveMessage1, 'RegistrationHandler');

$sensitiveMessage2 = 'Payment attempt with credit card number 1234-5678-9012-3456 failed. Customer ID: 789';
$logger->warning($sensitiveMessage2, 'PaymentGateway');

$sensitiveMessage3 = 'Admin requested user data for ssn 000-11-2222.';
$logger->info($sensitiveMessage3, 'AdminTool');

$sensitiveMessage4 = 'API call failed with token=abcfed1234567890. Payload: {...}';
$logger->error($sensitiveMessage4, 'ApiService');

$logger->info('Application shutdown gracefully.', 'AppLifecycle');

echo "Log entries generated in app.log\n";
?>