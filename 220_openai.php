<?php
namespace Inventory\Logging;

class InventoryLogger {
    private static $instance;
    private $logDir;
    private $logFile;
    private $maxFileSize;
    private $maxFiles;
    private $timezone;
    private $enabled;

    private function __construct(array $config = []) {
        $this->enabled = isset($config['enabled']) ? (bool)$config['enabled'] : true;

        $logDir = $config['log_dir'] ?? __DIR__ . '/../../logs';
        $logDir = rtrim($logDir, '/\\');
        $this->logDir = $logDir;

        $this->logFile = ltrim($config['log_file'] ?? 'inventory.log', '/\\');

        $this->maxFileSize = isset($config['max_file_size']) ? (int)$config['max_file_size'] : 5 * 1024 * 1024;
        $this->maxFiles = isset($config['max_rotated_files']) ? (int)$config['max_rotated_files'] : 7;

        $this->timezone = $config['timezone'] ?? date_default_timezone_get() ?? 'UTC';
        if (!in_array($this->timezone, timezone_identifiers_list(), true)) {
            $this->timezone = 'UTC';
        }

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0775, true);
        }

        if (function_exists('date_default_timezone_set')) {
            date_default_timezone_set($this->timezone);
        }
    }

    public static function init(array $config = []): InventoryLogger {
        if (!self::$instance) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function log(string $level, string $message, array $context = [], $userId = null, $username = null, $requestId = null, string $eventType = 'system'): bool {
        if (!$this->enabled) return false;

        $level = strtoupper($level);
        $valid = ['DEBUG','INFO','NOTICE','WARNING','ERROR','CRITICAL','ALERT','EMERGENCY'];
        if (!in_array($level, $valid, true)) {
            $level = 'INFO';
        }

        $logData = [
            'timestamp' => (new \DateTime('now', new \DateTimeZone($this->timezone)))->format('Y-m-d\\TH:i:sP'),
            'level' => $level,
            'user_id' => $userId,
            'username' => $username,
            'request_id' => $requestId,
            'event_type' => $eventType,
            'message' => $message,
            'context' => $context
        ];

        $encoded = json_encode($logData);
        if ($encoded === false) {
            $encoded = json_encode(['timestamp'=>date('c'),'level'=>$level,'message'=>$message]);
        }
        $line = $encoded . PHP_EOL;

        $this->rotateIfNeeded();
        $logPath = $this->logDir . DIRECTORY_SEPARATOR . $this->logFile;
        if (($fp = @fopen($logPath, 'a')) !== false) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $line);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
            return true;
        }
        $written = @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
        return $written !== false;
    }

    public function info(string $message, array $context = [], $userId = null, $username = null, $requestId = null, string $eventType = 'system'): bool {
        return $this->log('INFO', $message, $context, $userId, $username, $requestId, $eventType);
    }

    public function debug(string $message, array $context = [], $userId = null, $username = null, $requestId = null, string $eventType = 'system'): bool {
        return $this->log('DEBUG', $message, $context, $userId, $username, $requestId, $eventType);
    }

    public function error(string $message, array $context = [], $userId = null, $username = null, $requestId = null, string $eventType = 'system'): bool {
        return $this->log('ERROR', $message, $context, $userId, $username, $requestId, $eventType);
    }

    public function logUserEvent(string $level, string $message, $userId, $username, array $context = [], $requestId = null, string $eventType = 'user'): bool {
        return $this->log($level, $message, $context, $userId, $username, $requestId, $eventType);
    }

    public function logSystemEvent(string $level, string $message, array $context = [], $requestId = null, string $eventType = 'system'): bool {
        return $this->log($level, $message, $context, null, null, $requestId, $eventType);
    }

    private function rotateIfNeeded(): void {
        $logPath = $this->logDir . DIRECTORY_SEPARATOR . $this->logFile;
        if (!is_file($logPath)) return;
        if (filesize($logPath) < $this->maxFileSize) return;

        $rotated = $logPath . '.' . date('Ymd_His');
        if (!rename($logPath, $rotated)) return;

        $files = glob($logPath . '.*');
        if ($files === false) $files = [];
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        while (count($files) > $this->maxFiles) {
            $old = array_shift($files);
            if (is_file($old)) unlink($old);
        }
    }
}
?>