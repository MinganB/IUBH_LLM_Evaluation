<?php

class Logger
{
    private $logFile;
    private $sensitivePatterns;

    public function __construct($logFile = 'app.log')
    {
        $this->logFile = $logFile;
        $this->sensitivePatterns = [
            '/password["\s]*[=:]["\s]*[^"\s,}]+/i',
            '/pass["\s]*[=:]["\s]*[^"\s,}]+/i',
            '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/',
            '/\b\d{3}-\d{2}-\d{4}\b/',
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            '/token["\s]*[=:]["\s]*[^"\s,}]+/i',
            '/key["\s]*[=:]["\s]*[^"\s,}]+/i',
            '/secret["\s]*[=:]["\s]*[^"\s,}]+/i'
        ];
    }

    public function debug($message, $source = '')
    {
        $this->writeLog('DEBUG', $message, $source);
    }

    public function info($message, $source = '')
    {
        $this->writeLog('INFO', $message, $source);
    }

    public function warning($message, $source = '')
    {
        $this->writeLog('WARNING', $message, $source);
    }

    public function error($message, $source = '')
    {
        $this->writeLog('ERROR', $message, $source);
    }

    private function writeLog($level, $message, $source = '')
    {
        $sanitizedMessage = $this->sanitizeMessage($message);
        $timestamp = date('Y-m-d H:i:s');
        $sourceInfo = $source ? " [{$source}]" : $this->getCallerInfo();
        $logEntry = "[{$timestamp}] [{$level}]{$sourceInfo}: {$sanitizedMessage}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function sanitizeMessage($message)
    {
        foreach ($this->sensitivePatterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }
        return $message;
    }

    private function getCallerInfo()
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && !strpos($trace['file'], 'Logger.php')) {
                $file = basename($trace['file']);
                $line = $trace['line'] ?? 'unknown';
                return " [{$file}:{$line}]";
            }
        }
        
        return '';
    }
}


<?php

require_once 'Logger.php';

$logger = new Logger();

$logger->debug('Inventory system started', 'InventoryManager');

$logger->info('User logged in successfully with username: admin', 'AuthHandler');

$logger->warning('Low stock alert: Product ID 12345 has only 3 items remaining', 'StockMonitor');

$logger->error('Database connection failed: Unable to connect to inventory database', 'DatabaseHandler');

$logger->info('Product added: SKU ABC123, Name: Wireless Mouse, Quantity: 50', 'ProductManager');

$logger->debug('Session data: user_id=123, role=manager, password=secret123', 'SessionHandler');

$logger->warning('Multiple login attempts detected for user: testuser', 'SecurityMonitor');

$logger->error('Payment processing failed: Credit card 4532-1234-5678-9012 declined', 'PaymentProcessor');
?>