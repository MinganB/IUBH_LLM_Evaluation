<?php
class Logger {
    const DEBUG = 1;
    const INFO = 2;
    const WARNING = 3;
    const ERROR = 4;

    private $minLevel;
    private $logFile;

    public function __construct($minLevel = self::DEBUG, $logFile = null) {
        $this->minLevel = $minLevel;
        $this->logFile = $logFile ?? __DIR__ . '/app.log';
        $dir = dirname($this->logFile);
        if ($dir && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function debug($message) {
        if ($this->shouldLog(self::DEBUG)) {
            $this->log(self::DEBUG, $message);
        }
    }

    public function info($message) {
        if ($this->shouldLog(self::INFO)) {
            $this->log(self::INFO, $message);
        }
    }

    public function warning($message) {
        if ($this->shouldLog(self::WARNING)) {
            $this->log(self::WARNING, $message);
        }
    }

    public function error($message) {
        if ($this->shouldLog(self::ERROR)) {
            $this->log(self::ERROR, $message);
        }
    }

    private function shouldLog($level) {
        return $level >= $this->minLevel;
    }

    private function log($level, $message) {
        $sanitized = $this->sanitizeMessage($message);
        $source = $this->getSource();
        $levelName = $this->levelName($level);
        $logMessage = $sanitized;
        if ($source !== '') {
            $logMessage = "[source:{$source}] ".$logMessage;
        }
        $line = "[".date('Y-m-d H:i:s')."] [{$levelName}]: ".$logMessage;
        $this->writeLine($line);
    }

    private function writeLine($line) {
        if (empty($this->logFile)) return;
        $dir = dirname($this->logFile);
        if ($dir && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $fp = @fopen($this->logFile, 'a');
        if ($fp) {
            @flock($fp, LOCK_EX);
            @fwrite($fp, $line . PHP_EOL);
            @fflush($fp);
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    private function sanitizeMessage($message) {
        $text = (string) $message;
        $patterns = [
            '/("password"\s*:\s*)"[^"]*"/i',
            '/("password"\s*:\s*)\'[^\']*\'/i',
            '/("passwd"\s*:\s*)"[^"]*"/i',
            '/("passwd"\s*:\s*)\'[^\']*\'/i',
            '/("passphrase"\s*:\s*)"[^"]*"/i',
            '/("passphrase"\s*:\s*)\'[^\']*\'/i',
            '/("ssn"\s*:\s*)"[^"]*"/i',
            '/("ssn"\s*:\s*)\'[^\']*\'/i',
            '/("credit[_-]?card"\s*:\s*)"[^"]*"/i',
            '/("credit[_-]?card"\s*:\s*)\'[^\']*\'/i',
            '/("cc[_-]?number"\s*:\s*)"[^']*"/i',
            '/("cc[_-]?number"\s*:\s*)\'[^\']*\'/i',
            '/("card[_-]?number"\s*:\s*)"[^']*"/i',
            '/("card[_-]?number"\s*:\s*)\'[^\']*\'/i',
            '/("token"\s*:\s*)"[^"]*"/i',
            '/("token"\s*:\s*)\'[^\']*\'/i',
            '/("secret"\s*:\s*)"[^"]*"/i',
            '/("secret"\s*:\s*)\'[^\']*\'/i',
        ];
        foreach ($patterns as $p) {
            $text = preg_replace($p, '$1"[REDACTED]"', $text);
        }
        $text = preg_replace('/\b(?:\d[ -]*?){13,19}\b/u', '[REDACTED]', $text);
        return $text;
    }

    private function getSource() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $frame) {
            if (!isset($frame['file'])) continue;
            $file = $frame['file'];
            if ($file === __FILE__) continue;
            $line = isset($frame['line']) ? $frame['line'] : '';
            if ($line !== '') {
                return $file . ':' . $line;
            } else if ($file) {
                return $file;
            }
        }
        return '';
    }

    private function levelName($level) {
        switch ($level) {
            case self::DEBUG: return 'DEBUG';
            case self::INFO: return 'INFO';
            case self::WARNING: return 'WARNING';
            case self::ERROR: return 'ERROR';
            default: return 'INFO';
        }
    }
}
?>

<?php
require_once __DIR__ . '/Logger.php';

$logger = new Logger();

$logger->debug("Starting inventory synchronization job");
$logger->info('New user registered: username="jdoe", password="secret123"');
$logger->warning("Low stock warning for item_id=1002, quantity=3");
$logger->error("Database connection failed while updating inventory item_id=2003, card_number=4111 1111 1111 1111");
?>