<?php

class Logger
{
    private string $logFilePath;
    private array $logLevels = [
        'DEBUG'     => 100,
        'INFO'      => 200,
        'NOTICE'    => 250,
        'WARNING'   => 300,
        'ERROR'     => 400,
        'CRITICAL'  => 500,
        'ALERT'     => 550,
        'EMERGENCY' => 600,
    ];
    private int $minLogLevel = 100;

    public function __construct(string $logFilePath, string $minLogLevel = 'DEBUG')
    {
        $this->logFilePath = $logFilePath;
        $this->setMinLogLevel($minLogLevel);
        $this->ensureLogFileStructure();
    }

    private function ensureLogFileStructure(): void
    {
        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                error_log("Logger: Could not create log directory: " . $logDir);
            }
        }
        if (!file_exists($this->logFilePath)) {
            if (file_put_contents($this->logFilePath, '', FILE_APPEND | LOCK_EX) === false) {
                 error_log("Logger: Could not create or write to log file initially: " . $this->logFilePath);
            }
            if (!chmod($this->logFilePath, 0644)) {
                 error_log("Logger: Could not set permissions for log file: " . $this->logFilePath);
            }
        }
        if (!is_writable($this->logFilePath)) {
            error_log("Logger: Log file is not writable: " . $this->logFilePath);
        }
    }

    public function setMinLogLevel(string $level): void
    {
        $level = strtoupper($level);
        if (isset($this->logLevels[$level])) {
            $this->minLogLevel = $this->logLevels[$level];
        } else {
            error_log("Logger: Invalid log level specified: " . $level . ". Defaulting to DEBUG.");
            $this->minLogLevel = $this->logLevels['DEBUG'];
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        $level = strtoupper($level);

        if (!isset($this->logLevels[$level])) {
            error_log("Logger: Attempted to log with unknown level: " . $level);
            $level = 'UNKNOWN';
            $levelValue = 0;
        } else {
            $levelValue = $this->logLevels[$level];
        }

        if ($levelValue < $this->minLogLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] [%s] %s", $timestamp, $level, $message);

        if (!empty($context)) {
            $jsonContext = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($jsonContext === false) {
                $logEntry .= ' [CONTEXT_ERROR: ' . json_last_error_msg() . '] ' . var_export($context, true);
            } else {
                $logEntry .= ' ' . $jsonContext;
            }
        }
        
        $logEntry .= PHP_EOL;

        if (file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            error_log("Logger: Could not write to log file: " . $this->logFilePath . " Message: " . $message);
        }
    }
}
<?php

require_once __DIR__ . '/Logger.php';

$logFile = __DIR__ . '/logs/application.log';

$logger = new Logger($logFile, 'DEBUG');

$logger->debug('This is a debug message.', ['component' => 'AuthService']);
$logger->info('Application started successfully.');
$logger->notice('User settings updated for user ID 456.');
$logger->warning('Configuration file not found, using default settings.', ['config_file' => 'config.json']);
$logger->error('Failed to connect to the database.', ['error_code' => 500, 'exception_class' => 'PDOException']);
$logger->critical('Service is down! Immediate attention required.');
$logger->alert('High memory usage detected on server.', ['memory_usage_percent' => 95]);
$logger->emergency('System is completely unusable, entire system crashed.');

$productionLogger = new Logger(__DIR__ . '/logs/production.log', 'INFO');
$productionLogger->debug('This debug message will NOT be logged in production.log.');
$productionLogger->info('This info message WILL be logged in production.log.');
$productionLogger->error('An unhandled exception occurred in a production environment.');
?>