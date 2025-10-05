<?php
class Logger {
    private static $instance = null;
    private $logDir;
    private $dateFormat = 'Y-m-d';

    private function __construct($logDir = null) {
        $dir = $logDir ?: getenv('INVENTORY_LOG_DIR');
        if (!$dir) {
            $dir = dirname(__DIR__) . '/logs';
        }
        $this->logDir = rtrim($dir, DIRECTORY_SEPARATOR);
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0775, true);
        }
    }

    public static function getInstance($logDir = null) {
        if (self::$instance === null) {
            self::$instance = new self($logDir);
        }
        return self::$instance;
    }

    public function log($level, $message, array $context = []) {
        $level = strtoupper($level);
        $allowed = ['DEBUG','INFO','NOTICE','WARNING','ERROR','CRITICAL','ALERT','EMERGENCY'];
        if (!in_array($level, $allowed, true)) {
            $level = 'INFO';
        }

        $date = (new \DateTime())->format($this->dateFormat);
        $logFile = $this->logDir . DIRECTORY_SEPARATOR . 'inventory_log_' . $date . '.log';

        $ctx = $this->buildContext($context);
        $entry = [
            'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
            'level' => $level,
            'message' => $message,
            'context' => $ctx
        ];
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            $line = '{"timestamp":"' . (new \DateTime())->format(\DateTime::ATOM) . '","level":"' . $level . '","message":"' . addslashes($message) . '","context":{}}';
        }
        $line .= PHP_EOL;

        if (is_dir($this->logDir) && is_writable($this->logDir)) {
            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        } else {
            error_log($line);
        }
    }

    private function buildContext(array $context) {
        $ctx = $context;
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (isset($_SESSION['user_id'])) $ctx['user_id'] = $_SESSION['user_id'];
            if (isset($_SESSION['username'])) $ctx['username'] = $_SESSION['username'];
            if (function_exists('session_id')) {
                $ctx['session_id'] = session_id();
            }
        }
        $ctx['ip'] = $this->getClientIp();
        $ctx['uri'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : (PHP_SAPI === 'cli' ? 'CLI' : '');
        $ctx['script'] = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : basename(__FILE__);
        $ctx['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ctx['system'] = php_uname('n');
        if (!isset($ctx['environment'])) {
            $ctx['environment'] = defined('ENVIRONMENT') ? constant('ENVIRONMENT') : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'production');
        }
        return $ctx;
    }

    private function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) return $_SERVER['REMOTE_ADDR'];
        return (php_sapi_name() === 'cli') ? 'CLI' : 'UNKNOWN';
    }
}

function logEvent($level, $message, array $context = []) {
    $logger = Logger::getInstance();
    $logger->log($level, $message, $context);
}
?>