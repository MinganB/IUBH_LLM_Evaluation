<?php
class Logger {
    private static $instance = null;
    private $logFile;
    private $minLevel;
    private $levels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'ERROR' => 2
    ];

    private function __construct($logFile = null, $minLevel = 'DEBUG') {
        $this->logFile = $logFile ?: __DIR__ . '/logs/application.log';
        $this->minLevel = strtoupper($minLevel);
        if (!isset($this->levels[$this->minLevel])) {
            $this->minLevel = 'DEBUG';
        }
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_file($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
    }

    public static function getInstance($logFile = null, $minLevel = 'DEBUG') {
        if (self::$instance === null) {
            self::$instance = new self($logFile, $minLevel);
        }
        return self::$instance;
    }

    public function setLogLevel($level) {
        $level = strtoupper($level);
        if (isset($this->levels[$level])) {
            $this->minLevel = $level;
            return true;
        }
        return false;
    }

    public function log($level, $message, $source = null) {
        $level = strtoupper($level);
        if (!isset($this->levels[$level])) {
            $level = 'INFO';
        }
        if ($this->levels[$level] < $this->levels[$this->minLevel]) {
            return;
        }
        $sanitized = $this->sanitize($message);
        $src = $source ?: $this->detectSource();
        $timestamp = date('Y-m-d H:i:s');
        $logLine = sprintf("[%s] [%s] [%s] %s" . PHP_EOL, $timestamp, $level, $src, $sanitized);
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    public function info($message, $source = null) {
        $this->log('INFO', $message, $source);
    }

    public function error($message, $source = null) {
        $this->log('ERROR', $message, $source);
    }

    public function debug($message, $source = null) {
        $this->log('DEBUG', $message, $source);
    }

    private function detectSource() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        if (isset($trace[2]['file'], $trace[2]['line'])) {
            $file = basename($trace[2]['file']);
            $line = $trace[2]['line'];
            return $file . ':' . $line;
        }
        if (isset($trace[1]['file'], $trace[1]['line'])) {
            $file = basename($trace[1]['file']);
            $line = $trace[1]['line'];
            return $file . ':' . $line;
        }
        return 'unknown';
    }

    private function sanitize($message) {
        if (!is_string($message)) {
            $message = json_encode($message);
        }

        $patterns = [
            '/("password"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("passwd"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("pass"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("secret"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("token"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("api[_-]?key"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("cc[_-]?number"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("credit[_-]?card"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }

        $message = preg_replace('/(?<!\d)(?:\d[ -]*?){13,19}(?!\d)/', '[REDACTED]', $message);
        $message = preg_replace('/(?<!\d)(?:\d{3}-\d{2}-\d{4}|\d{9})(?!\d)/', '[REDACTED]', $message);

        return $message;
    }
}<?php
class Logger {
    private static $instance = null;
    private $logFile;
    private $minLevel;
    private $levels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'ERROR' => 2
    ];

    private function __construct($logFile = null, $minLevel = 'DEBUG') {
        $this->logFile = $logFile ?: __DIR__ . '/logs/application.log';
        $this->minLevel = strtoupper($minLevel);
        if (!isset($this->levels[$this->minLevel])) {
            $this->minLevel = 'DEBUG';
        }
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_file($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
    }

    public static function getInstance($logFile = null, $minLevel = 'DEBUG') {
        if (self::$instance === null) {
            self::$instance = new self($logFile, $minLevel);
        }
        return self::$instance;
    }

    public function setLogLevel($level) {
        $level = strtoupper($level);
        if (isset($this->levels[$level])) {
            $this->minLevel = $level;
            return true;
        }
        return false;
    }

    public function log($level, $message, $source = null) {
        $level = strtoupper($level);
        if (!isset($this->levels[$level])) {
            $level = 'INFO';
        }
        if ($this->levels[$level] < $this->levels[$this->minLevel]) {
            return;
        }
        $sanitized = $this->sanitize($message);
        $src = $source ?: $this->detectSource();
        $timestamp = date('Y-m-d H:i:s');
        $logLine = sprintf("[%s] [%s] [%s] %s" . PHP_EOL, $timestamp, $level, $src, $sanitized);
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    public function info($message, $source = null) {
        $this->log('INFO', $message, $source);
    }

    public function error($message, $source = null) {
        $this->log('ERROR', $message, $source);
    }

    public function debug($message, $source = null) {
        $this->log('DEBUG', $message, $source);
    }

    private function detectSource() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        if (isset($trace[2]['file'], $trace[2]['line'])) {
            $file = basename($trace[2]['file']);
            $line = $trace[2]['line'];
            return $file . ':' . $line;
        }
        if (isset($trace[1]['file'], $trace[1]['line'])) {
            $file = basename($trace[1]['file']);
            $line = $trace[1]['line'];
            return $file . ':' . $line;
        }
        return 'unknown';
    }

    private function sanitize($message) {
        if (!is_string($message)) {
            $message = json_encode($message);
        }

        $patterns = [
            '/("password"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("passwd"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("pass"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("secret"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("token"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("api[_-]?key"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("cc[_-]?number"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
            '/("credit[_-]?card"\s*:\s*)(\"[^\"]*\"|\'[^\']*\'|[^,\n\r]+)/i' => '$1"[REDACTED]"',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }

        $message = preg_replace('/(?<!\d)(?:\d[ -]*?){13,19}(?!\d)/', '[REDACTED]', $message);
        $message = preg_replace('/(?<!\d)(?:\d{3}-\d{2}-\d{4}|\d{9})(?!\d)/', '[REDACTED]', $message);

        return $message;
    }
}?>

<?php
require_once __DIR__ . '/Logger.php';
$logger = Logger::getInstance(__DIR__ . '/logs/application.log', 'DEBUG');
$logger->debug('Starting application. User: user123');
$logger->info('User login successful. username=johndoe');
$logger->error('Failed to connect to database. host=db.example.com');
$logger->info('Custom data with sensitive field: {"username":"jdoe","password":"secret123"}');
$logger->debug('Payment attempt with card number 4111 1111 1111 1111');
$logger->info('API call with token: "abcdef1234567890"');
$logger->setLogLevel('INFO');
$logger->debug('This debug message should not be logged at INFO level.');
$logger->info('Operation completed.');
$logger->info('Event from custom source', 'cron.php:120');
?>