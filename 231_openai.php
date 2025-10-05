<?php
class Logger {
    private $logFile;
    private $level;
    private $levels = [
        'DEBUG' => 1,
        'INFO'  => 2,
        'ERROR' => 3
    ];
    private $sensitiveKeys = [
        'password','passwd','secret','token','apikey','apiKey','creditcard','credit_card','cardnumber','cc','cvv','pin','ssn','authorization'
    ];

    public function __construct($logFile = null, $level = 'DEBUG') {
        if ($logFile === null) {
            $logFile = __DIR__ . '/application.log';
        }
        $this->logFile = $logFile;
        $this->setLevel($level);
        if (!file_exists($this->logFile)) {
            $dir = dirname($this->logFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($this->logFile, '');
        }
    }

    public function setLevel($level) {
        $level = strtoupper($level);
        if (!isset($this->levels[$level])) {
            $level = 'DEBUG';
        }
        $this->level = $level;
    }

    public function debug($message, $context = []) {
        $this->log('DEBUG', $message, $context);
    }

    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }

    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }

    private function log($level, $message, $context) {
        if ($this->levels[$level] < $this->levels[$this->level]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $source = $this->getSource();

        $sanitizedContext = $this->sanitizeContext($context);
        $contextStr = '';
        if (is_array($sanitizedContext) && !empty($sanitizedContext)) {
            $contextStr = ' | ' . json_encode($sanitizedContext);
        } elseif (is_string($sanitizedContext) && $sanitizedContext !== '') {
            $contextStr = ' | ' . $sanitizedContext;
        }

        $sanitizedMessage = $this->sanitizeText($message);

        $logLine = sprintf("[%s] [%s] [%s] %s%s", $timestamp, $level, $source, $sanitizedMessage, $contextStr);

        $this->writeLog($logLine);
    }

    private function getSource() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach ($trace as $frame) {
            if (isset($frame['file']) && basename($frame['file']) !== 'Logger.php') {
                $file = isset($frame['file']) ? $frame['file'] : '';
                $line = isset($frame['line']) ? $frame['line'] : '';
                return $file . ':' . $line;
            }
        }
        return 'unknown';
    }

    private function sanitizeContext($context) {
        if (!is_array($context)) {
            return $context;
        }
        return $this->sanitizeArray($context);
    }

    private function sanitizeArray($arr) {
        if (!is_array($arr)) return $arr;
        $result = [];
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->sanitizeArray($value);
            } else {
                $lowerKey = strtolower((string)$key);
                if ($this->isSensitiveKey($lowerKey)) {
                    $result[$key] = 'REDACTED';
                } else {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }

    private function isSensitiveKey($key) {
        foreach ($this->sensitiveKeys as $k) {
            if (strpos($key, strtolower($k)) !== false || $key === strtolower($k)) {
                return true;
            }
        }
        return false;
    }

    private function sanitizeText($text) {
        if (!is_string($text)) {
            return json_encode($text);
        }
        $patterns = [
            '/(?i)(password|passwd|secret|token)\s*[:=]\s*([^\s,;]+)/',
            '/(?i)(card|credit)\s*(number|cardNumber|card_number|cc)\s*[:=]\s*([0-9\s-]+)/',
            '/(?i)(password|passwd|secret|token)\s*=\s*([^\s&]+)/',
            '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/'
        ];
        foreach ($patterns as $p) {
            $text = preg_replace($p, '$1: REDACTED', $text);
        }
        return $text;
    }

    private function writeLog($line) {
        if (!$this->logFile) return;
        $fp = fopen($this->logFile, 'a');
        if ($fp) {
            flock($fp, LOCK_EX);
            fwrite($fp, $line . PHP_EOL);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
?> 

<?php
require_once __DIR__ . '/Logger.php';

$logger = new Logger(__DIR__ . '/inventory.log', 'DEBUG');

$logger->debug('Inventory module initialization', ['module' => 'Inventory', 'action' => 'init']);

$logger->info('User login', ['user' => 'alice', 'role' => 'manager', 'password' => 'secret123']);

$logger->error('Failed to update stock for item', ['itemId' => 1023, 'requested' => 10, 'available' => 2, 'creditCard' => '4111111111111111']);

$logger->info('New item added', ['item' => 'Gadget Pro', 'sku' => 'GP-100', 'quantity' => 25]);
?>