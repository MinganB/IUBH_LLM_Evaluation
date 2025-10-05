<?php
declare(strict_types=1);

namespace App\Logging;

class Logger {
    private static array $levelOrder = ['debug' => 0, 'info' => 1, 'error' => 2];
    private static int $minLevel = 0;
    private static string $logDir = __DIR__ . '/logs';
    private static string $logFile = '';

    public static function init(array $config = []): void {
        if (isset($config['min_level'])) {
            $lvl = strtolower($config['min_level']);
            if (isset(self::$levelOrder[$lvl])) {
                self::$minLevel = self::$levelOrder[$lvl];
            }
        }
        if (isset($config['log_dir']) && is_string($config['log_dir']) && $config['log_dir'] !== '') {
            self::$logDir = rtrim($config['log_dir'], DIRECTORY_SEPARATOR);
        }
        if (isset($config['log_file']) && is_string($config['log_file']) && $config['log_file'] !== '') {
            self::$logFile = rtrim($config['log_file'], DIRECTORY_SEPARATOR);
            $logDir = dirname(self::$logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
        }
        if (empty(self::$logFile)) {
            if (!is_dir(self::$logDir) && !@mkdir(self::$logDir, 0775, true) && !is_dir(self::$logDir)) {
                self::$logDir = sys_get_temp_dir();
            }
            $date = date('Y-m-d');
            self::$logFile = self::$logDir . DIRECTORY_SEPARATOR . 'application.log.' . $date;
        }
    }

    public static function log(string $level, $message, array $context = []): void {
        $level = strtolower($level);
        if (!isset(self::$levelOrder[$level])) {
            $level = 'info';
        }
        if (self::$levelOrder[$level] < self::$minLevel) {
            return;
        }

        $sanitizedMessage = self::sanitize($message);
        $sanitizedContext = self::sanitize($context);

        $source = self::detectSource();

        $timestamp = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s.u\\Z');
        $logLine = sprintf("[%s] [%s] [%s] %s", $timestamp, strtoupper($level), $source, is_string($sanitizedMessage) ? $sanitizedMessage : json_encode($sanitizedMessage));

        if (!empty($sanitizedContext)) {
            $contextString = json_encode($sanitizedContext);
            $logLine .= ' | context: ' . $contextString;
        }

        self::writeToFile($logLine);
    }

    private static function writeToFile(string $line): void {
        $logFile = self::$logFile;
        if (empty($logFile)) {
            return;
        }
        $lineWithNewline = $line . PHP_EOL;
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($logFile, $lineWithNewline, FILE_APPEND | LOCK_EX);
    }

    private static function detectSource(): string {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        foreach ($trace as $frame) {
            if (isset($frame['file']) && $frame['file'] !== __FILE__) {
                $file = $frame['file'] ?? '';
                $line = $frame['line'] ?? '';
                $filename = basename($file);
                if ($line) {
                    return $filename . ':' . $line;
                }
                return $filename;
            }
        }
        return 'unknown';
    }

    private static function sanitize($data) {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $k => $v) {
                $sanitized[$k] = self::sanitize($v);
            }
            return $sanitized;
        } elseif (is_object($data)) {
            if (method_exists($data, '__toString')) {
                return self::sanitize((string)$data);
            } else {
                return self::sanitize(json_decode(json_encode($data), true));
            }
        } elseif (is_string($data)) {
            $s = $data;
            $patterns = [
                '/(password|passwd|pwd)\s*[:=]\s*([^\s,;]+)/i',
                '/(token|api[_-]?key|authorization)\s*[:=]\s*[^\s,;]+/i',
                '/\b(?:\d[ -]*?){13,19}\b/',
                '/\b\d{3}-?\d{2}-?\d{4}\b/',
                '/CVV\s*[:=]?\s*\d+/i',
            ];
            foreach ($patterns as $regex) {
                $s = preg_replace($regex, 'REDACTED', $s);
            }
            $s = preg_replace('/("password"|"passwd"|"pwd"|"token"|"api[_-]?key"|"authorization")\s*:\s*"[^"]*"/i', '$1: "REDACTED"', $s);
            $s = preg_replace('/\b\d{15,}\b/', 'REDACTED', $s);
            if (strlen($s) > 32768) {
                $s = substr($s, 0, 32768) . '...';
            }
            return $s;
        } else {
            return $data;
        }
    }

    public static function info($message, array $context = []): void {
        self::log('info', $message, $context);
    }

    public static function debug($message, array $context = []): void {
        self::log('debug', $message, $context);
    }

    public static function error($message, array $context = []): void {
        self::log('error', $message, $context);
    }
}

Logger::init([
    'min_level' => 'debug',
    'log_dir' => __DIR__ . '/logs',
    'log_file' => __DIR__ . '/logs/app.log',
]);
?>