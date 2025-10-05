<?php
declare(strict_types=1);

namespace Inventory\Logging;

final class Logger
{
    private static ?Logger $instance = null;

    private string $logDir;
    private int $minLevel;
    private array $levelMap = [
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7,
    ];
    private array $sensitiveKeys = [
        'password','pwd','password_hash','card_number','credit_card','cc_number',
        'ssn','token','secret','api_key','authorization'
    ];

    private function __construct()
    {
        $root = dirname(__DIR__); // project root (parent of /classes)
        $this->logDir = rtrim($root . '/logs', '/');
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }

        $envLevel = getenv('LOG_MIN_LEVEL');
        if ($envLevel === false || $envLevel === '') {
            $envLevel = 'info';
        }
        $envLevel = strtolower($envLevel);
        $this->minLevel = $this->levelToInt($envLevel);
        if ($this->minLevel < 0) {
            $this->minLevel = $this->levelToInt('info');
        }
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        self::getInstance()->logInternal($level, $message, $context);
    }

    private static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function levelToInt(string $level): int
    {
        $level = strtolower($level);
        return $this->levelMap[$level] ?? $this->levelMap['info'];
    }

    private function logInternal(string $level, string $message, array $context): void
    {
        $level = strtolower($level);
        if (!isset($this->levelMap[$level])) {
            $level = 'info';
        }
        $levelValue = $this->levelMap[$level];
        if ($levelValue > $this->minLevel) {
            return;
        }

        $sanitizedMessage = $this->redactString($message);
        $sanitizedContext = $this->sanitizeArray($context);

        $source = $this->detectSource();

        $payload = [
            'ts'      => gmdate('Y-m-d\\TH:i:s\\Z'),
            'level'   => strtoupper($level),
            'source'  => $source,
            'message' => $sanitizedMessage,
            'context' => $sanitizedContext
        ];

        $logLine = '[' . gmdate('Y-m-d H:i:s') . '] ' .
                   strtoupper($level) . ' ' .
                   $source . ' ' .
                   json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $logFile = $this->getLogFilePath();
        if ($fh = fopen($logFile, 'a')) {
            if (flock($fh, LOCK_EX)) {
                fwrite($fh, $logLine . PHP_EOL);
                fflush($fh);
                flock($fh, LOCK_UN);
            }
            fclose($fh);
        }
    }

    private function getLogFilePath(): string
    {
        $date = date('Y-m-d');
        return $this->logDir . '/inventory-' . $date . '.log';
    }

    private function detectSource(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $frame) {
            if (isset($frame['file']) && $frame['file'] !== __FILE__) {
                $file = $frame['file'];
                $line = isset($frame['line']) ? $frame['line'] : '';
                return basename($file) . ':' . $line;
            }
        }
        return 'unknown';
    }

    private function sanitizeArray($data)
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $key = strtolower((string)$k);
                if (in_array($key, array_map('strtolower', $this->sensitiveKeys), true)) {
                    $out[$k] = '***REDACTED***';
                } else {
                    if (is_array($v)) {
                        $out[$k] = $this->sanitizeArray($v);
                    } elseif (is_object($v)) {
                        $out[$k] = $this->sanitizeObject($v);
                    } else {
                        $out[$k] = $this->redactValueFromString((string)$v);
                    }
                }
            }
            return $out;
        } elseif (is_object($data)) {
            return $this->sanitizeObject($data);
        } else {
            return $this->redactValueFromString((string)$data);
        }
    }

    private function sanitizeObject($obj)
    {
        if (is_object($obj)) {
            $arr = get_object_vars($obj);
            return $this->sanitizeArray($arr);
        }
        return $obj;
    }

    private function redactValueFromString(string $value): string
    {
        $patterns = [
            '/(password|pwd|token|secret|authorization|api_key)\s*[:=]\s*[^,\s]+/i',
        ];
        foreach ($patterns as $pat) {
            $value = preg_replace($pat, '$1: [REDACTED]', $value);
        }
        $value = preg_replace('/(?<!\d)(?:\d[ -]*?){13,19}(?!\d)/', '[REDACTED_NUM]', $value);
        return $value;
    }

    private function redactString(string $value): string
    {
        $value = preg_replace('/(password|pwd|token|secret|authorization|api_key)\s*[:=]\s*[^,\s]+/i', '$1: [REDACTED]', $value);
        $value = preg_replace('/(?<!\d)(?:\d[ -]*?){13,19}(?!\d)/', '[REDACTED_NUM]', $value);
        return $value;
    }
}
?>