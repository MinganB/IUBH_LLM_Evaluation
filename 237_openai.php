<?php
class Logger {
    private $logFile;
    private $minLevel;
    private $levels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3
    ];

    public function __construct($minLevel = 'debug', $logFileName = 'app.log') {
        $this->logFile = __DIR__ . '/' . $logFileName;
        $lvl = strtolower($minLevel);
        $this->minLevel = isset($this->levels[$lvl]) ? $lvl : 'debug';
    }

    public function debug($message) {
        $this->log('debug', $message);
    }

    public function info($message) {
        $this->log('info', $message);
    }

    public function warning($message) {
        $this->log('warning', $message);
    }

    public function error($message) {
        $this->log('error', $message);
    }

    private function log($level, $message) {
        if (!isset($this->levels[$level])) {
            return;
        }
        if ($this->levels[$level] < $this->levels[$this->minLevel]) {
            return;
        }

        $raw = (string)$message;
        $sanitized = $this->sanitize($raw);
        $source = $this->getSource();

        $levelUpper = strtoupper($level);
        $logLine = '[' . date('Y-m-d H:i:s') . '] [' . $levelUpper . ']: source=' . $source . ' | ' . $sanitized;

        $this->writeLog($logLine);
    }

    private function getSource() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($trace as $frame) {
            if (isset($frame['file']) && $frame['file'] !== __FILE__) {
                $file = isset($frame['file']) ? basename($frame['file']) : 'unknown';
                $line = isset($frame['line']) ? $frame['line'] : '';
                return $file . ':' . $line;
            }
        }
        return 'unknown';
    }

    private function sanitize($message) {
        $msg = $message;

        // Redact key=value patterns and common sensitive keys
        $patterns = [
            '/(\b(password|passwd|secret|token|api[_-]?key|authorization|credit|cc|card|cvv|pin|ssn)\b\s*[:=]\s*)(\"?[^\s\";]+\"?)/i',
            '/(\"(?:(?:password|secret|token|api[_-]?key|credit|cc|card|cvv|pin|ssn))\"?\s*:\s*)(\"?[^\",}]+\"?)/i'
        ];

        foreach ($patterns as $p) {
            $msg = preg_replace($p, '$1"[REDACTED]"', $msg);
        }

        // Redact JSON-like values: "key":"value" or "key": value
        $msg = preg_replace('/("(?:(?:password|secret|token|api[_-]?key|authorization|credit|cc|card|cvv|pin|ssn))"\s*:\s*)"[^"]*"/i', '$1"[REDACTED]"', $msg);

        // Redact long digit sequences that resemble card numbers
        $msg = preg_replace('/(?<!\d)(?:\d[ -]?){13,19}(?!\d)/', '[REDACTED]', $msg);

        $msg = preg_replace('/\s+/', ' ', $msg);

        return $msg;
    }

    private function writeLog($line) {
        $dir = dirname($this->logFile);
        if (!is_dir($dir) && $dir !== '.') {
            mkdir($dir, 0777, true);
        }

        if (!file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }

        $fp = fopen($this->logFile, 'a');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $line . PHP_EOL);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }
}
?> 

<?php
require_once 'Logger.php';

$logger = new Logger();

$logger->debug('Debugging application start. User input: username=demo, password=supersecret');
$logger->info('Application initialized. Connecting to service at https://service.example/api');
$logger->warning('Configuration warning detected in module loader.');
$logger->error('Unhandled exception occurred in processing pipeline. Error code 500. Attempted data: card_number=4111 1111 1111 1111'); 
?>