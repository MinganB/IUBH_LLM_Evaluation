<?php
class Logger
{
    const DEBUG = 1;
    const INFO = 2;
    const ERROR = 3;

    private $logDir;
    private $logFile;
    private $threshold;
    private $sensitiveKeys = ['password','passwd','pwd','credit_card','cc_number','ssn','token','api_key','secret','card_number'];

    public function __construct($logDir = null, $logFileName = 'inventory.log', $level = self::DEBUG)
    {
        if ($logDir === null) {
            $logDir = __DIR__ . '/../logs';
        }
        $this->logDir = rtrim($logDir, '/\\');
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0775, true);
        }
        $this->logFile = $this->logDir . '/' . $logFileName;
        $this->threshold = $level;
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
        }
    }

    public function debug($message, $context = [], $source = null)
    {
        $this->log(self::DEBUG, $message, $context, $source);
    }

    public function info($message, $context = [], $source = null)
    {
        $this->log(self::INFO, $message, $context, $source);
    }

    public function error($message, $context = [], $source = null)
    {
        $this->log(self::ERROR, $message, $context, $source);
    }

    public function log($level, $message, $context = [], $source = null)
    {
        if ($level < $this->threshold) {
            return;
        }
        $levelName = $this->levelName($level);
        $sanitizedContext = $this->sanitize($context);
        $timestamp = date('Y-m-d H:i:s');
        $sourcePart = $source ? "[$source]" : '';
        $logLine = "[$timestamp] [$levelName]{$sourcePart} {$message}";
        if (!empty($sanitizedContext)) {
            $logLine .= ' | ' . json_encode($sanitizedContext);
        }

        $this->writeLine($logLine);
    }

    private function levelName($level)
    {
        switch ($level) {
            case self::DEBUG:
                return 'DEBUG';
            case self::INFO:
                return 'INFO';
            case self::ERROR:
                return 'ERROR';
            default:
                return 'LOG';
        }
    }

    private function writeLine($line)
    {
        $fh = @fopen($this->logFile, 'a');
        if (!$fh) {
            return;
        }
        if (flock($fh, LOCK_EX)) {
            fwrite($fh, $line . PHP_EOL);
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
    }

    private function sanitize($data)
    {
        return $this->sanitizeValue(null, $data);
    }

    private function isSensitiveKey($key)
    {
        $k = strtolower((string)$key);
        foreach ($this->sensitiveKeys as $s) {
            if ($k === $s || strpos($k, $s) !== false) {
                return true;
            }
        }
        return false;
    }

    private function sanitizeValue($key, $value)
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $k => $v) {
                $sanitized[$k] = $this->sanitizeValue($k, $v);
            }
            return $sanitized;
        } elseif (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            } else {
                return json_decode(json_encode($value), true);
            }
        } elseif (is_string($value)) {
            if ($key !== null && $this->isSensitiveKey($key)) {
                return '[REDACTED]';
            }
            $redacted = preg_replace('/\b(?:\d[ -]*?){12,19}\b/', '[REDACTED]', $value);
            return $redacted;
        } else {
            return $value;
        }
    }
}
?> 

<?php
$loggerPath = __DIR__ . '/../classes/Logger.php';
if (!file_exists($loggerPath)) {
    $loggerPath = __DIR__ . '/classes/Logger.php';
}
require_once $loggerPath;

$logDir = __DIR__ . '/../logs';
$logger = new Logger($logDir, 'inventory.log', Logger::DEBUG);

$logger->info("User login attempt", ['username' => 'alice', 'ip' => '192.168.1.50', 'password' => 'hunter2'], 'AuthHandler');
$logger->debug("Created new inventory item", ['item_id' => 101, 'name' => 'Widget', 'price' => 9.99, 'credit_card' => '4111 1111 1111 1111'], 'InventoryHandler');
$logger->error("Database connection failed", ['host' => 'db01', 'port' => 3306, 'exception' => 'Connection timed out'], 'DatabaseHandler');
?>