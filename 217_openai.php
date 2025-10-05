<?php
class Logger {
  private static $LEVELS = [
    'emergency' => 0,
    'alert' => 1,
    'critical' => 2,
    'error' => 3,
    'warning' => 4,
    'notice' => 5,
    'info' => 6,
    'debug' => 7,
  ];

  private $logDirectory;
  private $logFileName;
  private $logFilePath;
  private $logLevel;
  private $maxFileSize;
  private $maxBackups;
  private $dateFormat;
  private $useStdout;

  public function __construct(
    string $logDirectory = '',
    string $logFileName = 'app.log',
    int $level = 6,
    int $maxFileSize = 5242880,
    int $maxBackups = 5,
    string $dateFormat = 'Y-m-d H:i:s',
    bool $useStdout = false
  ) {
    if ($logDirectory === '') {
      $logDirectory = __DIR__ . '/logs';
    }
    if (!is_dir($logDirectory)) {
      @mkdir($logDirectory, 0777, true);
    }
    $this->logDirectory = rtrim($logDirectory, '/\\');
    $this->logFileName = $logFileName;
    $this->logFilePath = $this->logDirectory . '/' . $this->logFileName;
    if ($level < 0 || $level > 7) {
      $level = 6;
    }
    $this->logLevel = $level;
    $this->maxFileSize = max(0, (int)$maxFileSize);
    $this->maxBackups = max(0, (int)$maxBackups);
    $this->dateFormat = $dateFormat;
    $this->useStdout = $useStdout;
  }

  public function log(string $level, string $message, array $context = []): bool {
    $l = strtolower($level);
    if (!isset(self::$LEVELS[$l])) {
      $l = 'info';
    }
    $levelValue = self::$LEVELS[$l];
    if ($levelValue > $this->logLevel) {
      return false;
    }
    $interpolated = $this->interpolate($message, $context);
    $line = sprintf("[%s] %s: %s", (new DateTime())->format($this->dateFormat), strtoupper($l), $interpolated);

    if ($this->maxFileSize > 0) {
      $this->rotateIfNeeded();
    }

    $written = false;
    $fp = @fopen($this->logFilePath, 'a');
    if ($fp !== false) {
      if (flock($fp, LOCK_EX)) {
        fwrite($fp, $line . PHP_EOL);
        fflush($fp);
        flock($fp, LOCK_UN);
      }
      fclose($fp);
      $written = true;
    }

    if (!$written && $this->useStdout) {
      echo $line . PHP_EOL;
      $written = true;
    }

    return $written;
  }

  private function interpolate(string $message, array $context): string {
    foreach ($context as $key => $value) {
      $placeholder = '{' . $key . '}';
      $replacement = is_scalar($value) ? (string)$value : json_encode($value);
      $message = str_replace($placeholder, $replacement, $message);
    }
    return $message;
  }

  private function rotateIfNeeded(): void {
    if ($this->maxFileSize <= 0) return;
    if (!file_exists($this->logFilePath)) return;
    clearstatcache(true, $this->logFilePath);
    $size = filesize($this->logFilePath);
    if ($size <= $this->maxFileSize) return;

    if ($this->maxBackups <= 0) {
      rename($this->logFilePath, $this->logFilePath . '.1');
      return;
    }

    for ($i = $this->maxBackups; $i >= 1; $i--) {
      $src = $this->logFilePath . ($i == 1 ? '' : '.' . $i);
      $dst = $this->logFilePath . '.' . ($i + 1);
      if (file_exists($src)) {
        rename($src, $dst);
      }
    }
    rename($this->logFilePath, $this->logFilePath . '.1');
  }
}
function log_message(string $level, string $message, array $context = []): bool {
  static $logger = null;
  if ($logger === null) {
    $logger = new Logger();
  }
  return $logger->log($level, $message, $context);
}
function log_info(string $message, array $context = []): bool {
  return log_message('info', $message, $context);
}
function log_error(string $message, array $context = []): bool {
  return log_message('error', $message, $context);
}
function log_debug(string $message, array $context = []): bool {
  return log_message('debug', $message, $context);
}
function log_warning(string $message, array $context = []): bool {
  return log_message('warning', $message, $context);
}
?>