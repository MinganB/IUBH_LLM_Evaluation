<?php
class Logger {
    private static $levels = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7
    ];
    private $timezone;
    private function __construct() {
        $tz = @date_default_timezone_get();
        $this->timezone = $tz ? $tz : 'UTC';
        if (!is_dir(__DIR__ . '/../logs')) {
            @mkdir(__DIR__ . '/../logs', 0770, true);
        }
    }
    public static function log(string $level, string $message, array $context = []): bool {
        $lvl = strtolower($level);
        if (!isset(self::$levels[$lvl])) {
            $lvl = 'info';
        }
        $self = new self();
        return $self->write($lvl, $message, $context);
    }
    private function write(string $level, string $message, array $context): bool {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            if (!@mkdir($logDir, 0770, true)) {
                error_log($message);
                return false;
            }
        }
        $logFile = $logDir . '/inventory.log.' . date('Y-m-d');
        $line = $this->formatLine($level, $message, $this->sanitizeContext($context));
        $fh = @fopen($logFile, 'a');
        if ($fh === false) {
            error_log($line);
            return false;
        }
        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            return false;
        }
        fwrite($fh, $line . PHP_EOL);
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        return true;
    }
    private function formatLine(string $level, string $message, array $context): string {
        $ts = (new DateTime('now', new DateTimeZone($this->timezone)))->format('Y-m-d H:i:s');
        $payload = [
            'ts' => $ts,
            'level' => $level,
            'message' => $message,
            'context' => $context ?: new stdClass()
        ];
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    private function sanitizeContext(array $data): array {
        $masked = $this->maskSensitive($data);
        $auto = $this->collectAutoContext();
        return array_merge($auto, $masked);
    }
    private function collectAutoContext(): array {
        $ctx = [];
        if (isset($_SERVER['REMOTE_ADDR'])) $ctx['ip'] = $_SERVER['REMOTE_ADDR'];
        if (isset($_SERVER['REQUEST_URI'])) $ctx['request'] = $_SERVER['REQUEST_URI'];
        if (isset($_SERVER['HTTP_USER_AGENT'])) $ctx['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        if (isset($_SERVER['REQUEST_METHOD'])) $ctx['method'] = $_SERVER['REQUEST_METHOD'];
        if (function_exists('php_sapi_name')) $ctx['sapi'] = php_sapi_name();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $ctx['session_id'] = session_id();
        }
        if (isset($_COOKIE['PHPSESSID'])) $ctx['php_session'] = $_COOKIE['PHPSESSID'];
        $ctx['app'] = 'Inventory Management System';
        return $ctx;
    }
    private function maskSensitive($data) {
        if (!is_array($data)) return $data;
        $out = [];
        foreach ($data as $k => $v) {
            $key = strtolower((string)$k);
            if (is_array($v)) {
                $out[$k] = $this->maskSensitive($v);
            } else {
                if (in_array($key, ['password','pwd','token','api_key','secret','credit_card','ssn','cvv'], true)) {
                    $out[$k] = '[REDACTED]';
                } else {
                    $out[$k] = $v;
                }
            }
        }
        return $out;
    }
}
function log_message(string $level, string $message, array $context = []): bool {
    return Logger::log($level, $message, $context);
}
?>