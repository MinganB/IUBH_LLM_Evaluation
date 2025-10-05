<?php
class Logger {
    private $logFile;

    public function __construct($logFile = null) {
        $this->logFile = $logFile ?: __DIR__ . '/app.log';
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_file($this->logFile)) {
            touch($this->logFile);
        }
    }

    private function redact($text) {
        if (!is_string($text)) {
            if (is_array($text) || is_object($text)) {
                $text = print_r($text, true);
            } else {
                $text = (string)$text;
            }
        }

        $text = preg_replace('/(?i)(password|passwd|pwd|secret|token)\s*[:=]\s*(?:\"[^\"]*\"|\'[^\']*\'|[^,\s]+)/', '$1: [REDACTED]', $text);
        $text = preg_replace('/(?i)"(password|passwd|pwd|secret|token)"\s*:\s*(?:\"[^\"]*\"|\'[^\']*\'|[^,\s]+)/', '"$1": "[REDACTED]"', $text);
        $text = preg_replace('/\b(?:\d[ -]*?){13,19}\b/', '[REDACTED_CARD]', $text);
        $text = preg_replace('/\b\d{3}-\d{2}-\d{4}\b/', '[REDACTED_SSN]', $text);
        return $text;
    }

    private function writeLog($level, $message, $source = null) {
        $timestamp = date('Y-m-d H:i:s');
        $sourcePrefix = $source ? "[SOURCE: {$source}] " : '';
        $sanitized = $this->redact($message);
        $logLine = "[{$timestamp}] [{$level}]: {$sourcePrefix}{$sanitized}\n";
        if ($this->logFile) {
            $dir = dirname($this->logFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (is_writable($dir)) {
                file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
            }
        }
    }

    public function debug($message, $source = null) {
        $this->writeLog('DEBUG', $message, $source);
    }

    public function info($message, $source = null) {
        $this->writeLog('INFO', $message, $source);
    }

    public function warning($message, $source = null) {
        $this->writeLog('WARNING', $message, $source);
    }

    public function error($message, $source = null) {
        $this->writeLog('ERROR', $message, $source);
    }
}
?>


<?php
require_once __DIR__ . '/classes/Logger.php';

$logger = new Logger();

$logger->debug('Debug: Inventory item retrieval started', 'InventoryService');
$logger->info('Info: User JohnDoe logged in', 'AuthModule');
$logger->warning('Warning: Low stock for item SKU 12345', 'InventoryService');
$logger->error('Error: Failed to process order for user user@example.com, password=supersecret', 'OrderProcessor');
?>