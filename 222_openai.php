<?php
declare(strict_types=1);

class InventoryLogger {
    private static $instance;
    private $logFile;
    private $minLevel;
    private $levelMap = [
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7
    ];

    private function __construct(array $config = []) {
        $this->logFile = $config['log_file'] ?? (__DIR__ . DIRECTORY_SEPARATOR . 'inventory.log');
        $configuredLevel = isset($config['min_level']) ? strtolower($config['min_level']) : 'debug';
        if (isset($this->levelMap[$configuredLevel])) {
            $this->minLevel = $configuredLevel;
        } else {
            $this->minLevel = 'debug';
        }
        $dir = dirname($this->logFile);
        if ($dir && !is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    public static function getInstance(array $config = []) {
        if (self::$instance === null) {
            self::$instance = new self($config);
        } else if (!empty($config)) {
            self::$instance->configure($config);
        }
        return self::$instance;
    }

    public function configure(array $config) {
        if (isset($config['log_file'])) {
            $this->logFile = $config['log_file'];
            $dir = dirname($this->logFile);
            if ($dir && !is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
        if (isset($config['min_level'])) {
            $lvl = strtolower($config['min_level']);
            if (isset($this->levelMap[$lvl])) {
                $this->minLevel = $lvl;
            }
        }
    }

    public function log(string $level, string $message, string $source = 'system', $context = []) {
        $level = strtolower($level);
        if (!isset($this->levelMap[$level])) {
            $level = 'info';
        }
        if ($this->levelMap[$level] > $this->levelMap[$this->minLevel]) {
            return;
        }

        $sanitizedMessage = $this->sanitizeString($message);
        $sanitizedContext = $this->sanitizeContext($context);

        $timestamp = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s\\Z');
        $logLine = sprintf("[%s] [%s] [%s] %s", $timestamp, strtoupper($level), $source, $sanitizedMessage);
        if (!empty($sanitizedContext)) {
            $logLine .= ' ' . json_encode($sanitizedContext, JSON_UNESCAPED_SLASHES);
        }

        $this->writeLine($logLine);
    }

    private function writeLine(string $line) {
        if ($this->logFile) {
            $dir = dirname($this->logFile);
            if ($dir && !is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (@file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
                error_log($line);
            } else {
                @chmod($this->logFile, 0640);
            }
        } else {
            error_log($line);
        }
    }

    private function sanitizeString(string $message): string {
        $output = $message;
        $patterns = [
            '/(?i)(password|passwd|pwd|secret|token|api[_-]?key|authorization)\s*[:=]\s*("[^"]+"|\'[^\']+\'|\S+)/' => '$1: [REDACTED]',
            '/(?i)(credit\s*card|cc|card)\s*(?:number)?\s*[:=]\s*([^\s,;]+)/' => '$1: [REDACTED]'
        ];
        foreach ($patterns as $regex => $replacement) {
            $output = preg_replace($regex, $replacement, $output);
        }
        $output = preg_replace('/\b\d{13,19}\b/', '[REDACTED]', $output);
        $output = preg_replace('/\b(?:\d[ -]*?){13,19}\b/', '[REDACTED]', $output);
        return $output;
    }

    private function sanitizeContext($context) {
        if ($context === null) return [];
        if (is_array($context)) {
            return $this->sanitizeArray($context);
        }
        if (is_object($context)) {
            return $this->sanitizeArray(get_object_vars($context));
        }
        return ['context' => $context];
    }

    private function sanitizeArray(array $arr) {
        foreach ($arr as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $arr[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $arr[$key] = $this->sanitizeArray($value);
            } else {
                if (is_string($value)) {
                    $arr[$key] = $this->sanitizeString($value);
                } else {
                    $arr[$key] = $value;
                }
            }
        }
        return $arr;
    }

    private function isSensitiveKey(string $key): bool {
        $k = strtolower($key);
        $keywords = ['password','passwd','pwd','secret','token','api_key','apikey','authorization','credit','cc','card','pin','ssn','ssn_number','iban','bic','cvv','otp'];
        foreach ($keywords as $kw) {
            if (strpos($k, $kw) !== false) {
                return true;
            }
        }
        return false;
    }
}

function logMessage(string $level, string $message, string $source = 'system', $context = []) {
    $logger = InventoryLogger::getInstance();
    $logger->log($level, $message, $source, $context);
}
?>