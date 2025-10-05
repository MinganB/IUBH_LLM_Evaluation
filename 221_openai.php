<?php
class InventoryLogger {
    private static $instance = null;

    private $config;
    private function __construct(array $config = []) {
        $default = [
            'path' => __DIR__ . '/logs/inventory.log',
            'level' => 'debug',
            'maxFileSize' => 5 * 1024 * 1024, // 5 MB
            'maxFiles' => 7,
            'dateFormat' => 'c' // ISO 8601
        ];
        $this->config = array_merge($default, $config);
        $this->ensureLogDirectory();
    }

    private function ensureLogDirectory(): void {
        $dir = dirname($this->config['path']);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_writable($dir)) {
            // Attempt to set permissions if possible
            @chmod($dir, 0775);
        }
        if (!file_exists($this->config['path'])) {
            @touch($this->config['path']);
            @chmod($this->config['path'], 0644);
        }
    }

    public static function getInstance(array $config = []): InventoryLogger {
        if (self::$instance === null) {
            self::$instance = new InventoryLogger($config);
        }
        return self::$instance;
    }

    public static function log(string $level, string $message, array $context = []): bool {
        $logger = self::getInstance();
        return $logger->logInternal($level, $message, $context);
    }

    public static function info(string $message, array $context = []): bool {
        return self::log('info', $message, $context);
    }

    public static function error(string $message, array $context = []): bool {
        return self::log('error', $message, $context);
    }

    public static function debug(string $message, array $context = []): bool {
        return self::log('debug', $message, $context);
    }

    public static function logUserAction(string $userId, string $username, string $action, string $itemId, int $quantity, array $additional = []): bool {
        $message = "User action: $action on item $itemId by $username";
        $context = [
            'user' => [
                'id' => $userId,
                'name' => $username
            ],
            'action' => $action,
            'item_id' => $itemId,
            'quantity' => $quantity
        ];
        if (!empty($additional)) {
            $context['additional'] = $additional;
        }
        return self::log('info', $message, $context);
    }

    public static function logSystem(string $level, string $message, array $context = []): bool {
        return self::log($level, $message, $context);
    }

    private function logInternal(string $level, string $message, array $context): bool {
        $level = strtolower($level);
        $sev = $this->levelToInt($level);
        if ($sev === null) {
            return false;
        }

        $minLevel = $this->levelToInt($this->config['level']);
        if ($sev > $minLevel) {
            return true; // below threshold; skip logging
        }

        $line = $this->formatLine($level, $message, $context);
        return $this->appendLineToFile($line);
    }

    private function formatLine(string $level, string $message, array $context): string {
        $entry = [
            'timestamp' => (new DateTime())->format($this->config['dateFormat']),
            'level' => $level,
            'message' => $message,
            'context' => $this->sanitizeContext($context)
        ];
        $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $entry['context'] = ['unserializable_context' => true];
            $encoded = json_encode($entry);
        }
        return $encoded;
    }

    private function appendLineToFile(string $line): bool {
        $logFile = $this->config['path'];
        $this->rotateIfNeeded();

        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true)) {
                return false;
            }
        }

        $fh = @fopen($logFile, 'a');
        if ($fh === false) {
            return false;
        }

        if (flock($fh, LOCK_EX)) {
            fwrite($fh, $line . PHP_EOL);
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        return true;
    }

    private function rotateIfNeeded(): void {
        $logFile = $this->config['path'];
        if (!file_exists($logFile)) {
            return;
        }
        $size = @filesize($logFile);
        if ($size === false) {
            return;
        }
        if ($size <= $this->config['maxFileSize']) {
            return;
        }

        $dir = dirname($logFile);
        $base = basename($logFile);
        $rotatedName = $logFile . '.' . date('YmdHis');
        @rename($logFile, $rotatedName);
        @touch($logFile);
        @chmod($logFile, 0644);
        $this->cleanupOldRotations($dir, $base);
    }

    private function cleanupOldRotations(string $dir, string $base): void {
        $files = scandir($dir);
        $rotations = [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            if (strpos($f, $base . '.') === 0) {
                $full = $dir . DIRECTORY_SEPARATOR . $f;
                $rotations[$full] = filemtime($full);
            }
        }

        if (count($rotations) <= (int)$this->config['maxFiles']) {
            return;
        }

        asort($rotations);
        $index = 0;
        foreach ($rotations as $path => $mtime) {
            $index++;
            if ($index > (int)$this->config['maxFiles']) {
                @unlink($path);
            }
        }
    }

    private function levelToInt(string $level): ?int {
        $levels = [
            'emergency' => 0,
            'alert' => 1,
            'critical' => 2,
            'error' => 3,
            'warning' => 4,
            'info' => 5,
            'debug' => 6
        ];
        return $levels[$level] ?? null;
    }

    private function sanitizeContext(array $context): array {
        $keys = ['password','passwd','pwd','token','apiKey','apikey','secret','api_secret','authorization'];
        $sanitized = [];
        foreach ($context as $k => $v) {
            $lower = strtolower((string)$k);
            if (in_array($lower, $keys, true)) {
                $sanitized[$k] = 'REDACTED';
            } elseif (is_array($v)) {
                $sanitized[$k] = $this->sanitizeContext($v);
            } else {
                $sanitized[$k] = $v;
            }
        }
        return $sanitized;
    }
}
?>