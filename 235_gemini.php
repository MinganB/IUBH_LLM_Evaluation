<?php

class Logger
{
    private string $logFilePath;

    public function __construct(string $logFileName = 'app.log')
    {
        $this->logFilePath = __DIR__ . DIRECTORY_SEPARATOR . $logFileName;
    }

    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$level]: $message\n";
        file_put_contents($this->logFilePath, $formattedMessage, FILE_APPEND | LOCK_EX);
    }

    public function debug(string $message): void
    {
        $this->writeLog('DEBUG', $message);
    }

    public function info(string $message): void
    {
        $this->writeLog('INFO', $message);
    }

    public function warning(string $message): void
    {
        $this->writeLog('WARNING', $message);
    }

    public function error(string $message): void
    {
        $this->writeLog('ERROR', $message);
    }
}

?>
<?php

require_once 'Logger.php';

$logger = new Logger('app.log');

$logger->debug('This is a debug message, useful for developers.');
$logger->info('This is an informational message, something expected happened.');
$logger->warning('This is a warning message, something might be wrong but it is not critical.');
$logger->error('This is an error message, a critical failure occurred.');

echo 'Log messages written to app.log' . PHP_EOL;

?>