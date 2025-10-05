<?php

class Logger
{
    private $logFile = 'app.log';
    private $sensitivePatterns = [
        '/password["\'\s]*[:=]["\'\s]*[^\s"\']+/i',
        '/pwd["\'\s]*[:=]["\'\s]*[^\s"\']+/i',
        '/pass["\'\s]*[:=]["\'\s]*[^\s"\']+/i',
        '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/',
        '/\b\d{3}-\d{2}-\d{4}\b/',
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/'
    ];

    public function debug($message)
    {
        $this->writeLog('DEBUG', $message);
    }

    public function info($message)
    {
        $this->writeLog('INFO', $message);
    }

    public function warning($message)
    {
        $this->writeLog('WARNING', $message);
    }

    public function error($message)
    {
        $this->writeLog('ERROR', $message);
    }

    private function writeLog($level, $message)
    {
        $sanitizedMessage = $this->sanitizeMessage($message);
        $timestamp = date('Y-m-d H:i:s');
        $source = $this->getSource();
        $logEntry = "[{$timestamp}] [{$level}]: {$sanitizedMessage} - Source: {$source}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function sanitizeMessage($message)
    {
        foreach ($this->sensitivePatterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }
        return $message;
    }

    private function getSource()
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = isset($backtrace[2]) ? $backtrace[2] : $backtrace[1];
        $file = isset($caller['file']) ? basename($caller['file']) : 'unknown';
        $line = isset($caller['line']) ? $caller['line'] : 'unknown';
        return "{$file}:{$line}";
    }
}
?>


<?php

require_once 'Logger.php';

$logger = new Logger();

$logger->debug('Inventory system started - checking database connection');

$logger->info('User login successful for username: admin');

$logger->info('Product added to inventory - SKU: LAPTOP001, Quantity: 25, Price: $899.99');

$logger->warning('Low stock alert - Product SKU: MOUSE004 has only 3 units remaining');

$logger->error('Database connection failed - unable to update inventory records');

$logger->info('Attempting to log sensitive data: password=secret123 and card=4532-1234-5678-9012');

$logger->warning('Multiple failed login attempts detected for user: testuser');

$logger->debug('Inventory report generated successfully - 150 products processed');

$logger->error('Payment processing failed - transaction ID: TXN789456123');

$logger->info('Daily backup completed - 2.3GB of data archived');

?>