<?php
class Logger {
    private static $instance = null;
    private $logDir;
    private $logFilenameBase;
    private $minLevel;
    private $levels = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ];

    private function __construct(array $config = []) {
        $dir = $config['log_dir'] ?? getenv('LOG_DIR') ?? (__DIR__ . '/logs');
        $base = $config['log_filename'] ?? $config['log_file'] ?? getenv('LOG_FILENAME') ?? 'app';
        $min = $config['min_level'] ?? getenv('LOG_MIN_LEVEL') ?? 'debug';
        if (!isset($this->levels[$min])) {
            $min = 'debug';
        }
        $this->logDir = rtrim($dir, DIRECTORY_SEPARATOR);
        $this->logFilenameBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $base);
        $this->minLevel = $this->levels[$min];
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }
    }

    public static function getInstance(array $config = []) {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function log(string $level, string $message, array $context = []) {
        $level = strtolower($level);
        if (!isset($this->levels[$level])) {
            $level = 'info';
        }
        if ($this->levels[$level] > $this->minLevel) {
            return;
        }
        $ts = microtime(true);
        $sec = (int)$ts;
        $usec = (int)(($ts - $sec) * 1000000);
        $timestamp = date('Y-m-d H:i:s', $sec) . '.' . sprintf('%06d', $usec);

        $payload = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $this->interpolate($message, $context),
            'context' => $context,
            'pid' => function_exists('getmypid') ? getmypid() : -1,
            'request_id' => $_SERVER['REQUEST_ID'] ?? ($_SESSION['REQUEST_ID'] ?? ''),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            $backup = ['timestamp' => $timestamp, 'level' => $level, 'message' => '[log encoding failed]'];
            $line = json_encode($backup);
        }
        $this->writeToFile($line);
    }

    private function interpolate(string $message, array $context): string {
        if (empty($context)) {
            return $message;
        }
        $placeholders = [];
        foreach ($context as $key => $value) {
            $placeholders['{' . $key . '}'] = $this->stringify($value);
        }
        return strtr($message, $placeholders);
    }

    private function stringify($value): string {
        if (is_scalar($value)) {
            return (string)$value;
        }
        if (is_null($value)) {
            return 'null';
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($json !== false) return $json;
        return '[object]';
    }

    private function writeToFile(string $line) {
        $path = $this->logDir . DIRECTORY_SEPARATOR . $this->logFilenameBase . '-' . date('Y-m-d') . '.log';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line .= PHP_EOL;
        $fp = @fopen($path, 'a');
        if ($fp === false) {
            if (function_exists('openlog') && function_exists('syslog')) {
                openlog('php-app', LOG_PID | LOG_ODELAY, LOG_USER);
                syslog(LOG_INFO, rtrim($line));
                closelog();
            }
            return;
        }
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $line);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    public function emergency($message, array $context = []) { $this->log('emergency', $message, $context); }
    public function alert($message, array $context = []) { $this->log('alert', $message, $context); }
    public function critical($message, array $context = []) { $this->log('critical', $message, $context); }
    public function error($message, array $context = []) { $this->log('error', $message, $context); }
    public function warning($message, array $context = []) { $this->log('warning', $message, $context); }
    public function notice($message, array $context = []) { $this->log('notice', $message, $context); }
    public function info($message, array $context = []) { $this->log('info', $message, $context); }
    public function debug($message, array $context = []) { $this->log('debug', $message, $context); }

    public static function logGlobal(string $level, string $message, array $context = []) {
        $logger = self::getInstance();
        $logger->log($level, $message, $context);
    }
}
?>