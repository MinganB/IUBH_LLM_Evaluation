<?php

class Logger
{
    private string $logFilePath;
    private int $minLogLevel;
    private array $logLevels = [
        'emergency' => 600,
        'alert'     => 550,
        'critical'  => 500,
        'error'     => 400,
        'warning'   => 300,
        'notice'    => 250,
        'info'      => 200,
        'debug'     => 100,
    ];

    private array $sensitiveKeywords = [
        'password', 'pass', 'pwd', 'credit_card', 'cc_number', 'card_number',
        'cvv', 'security_code', 'ssn', 'social_security', 'pin', 'bank_account_number',
        'routing_number', 'iban', 'swift', 'bic', 'api_key', 'auth_token', 'private_key'
    ];

    private array $sensitivePatterns = [
        '/\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9]{2})[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})\b/' => '[CC_REDACTED]',
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/' => '[EMAIL_REDACTED]',
        '/\b\d{3}-\d{2}-\d{4}\b/' => '[SSN_REDACTED]',
        '/(["\']?token["\']?\s*:\s*["\'])([A-Za-z0-9\-_=]+\.[A-Za-z0-9\-_=]+\.?[A-Za-z0-9\-_=]+)(["\'])/' => '$1[TOKEN_REDACTED]$3',
        '/(["\']?secret["\']?\s*:\s*["\'])([A-Za-z0-9\-_=]{16,})(["\'])/' => '$1[SECRET_REDACTED]$3',
    ];

    public function __construct(string $logFilePath, string $minLogLevel = 'info')
    {
        $this->logFilePath = $logFilePath;
        $this->minLogLevel = $this->logLevels[strtolower($minLogLevel)] ?? $this->logLevels['info'];

        $logDir = dirname($logFilePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);
        if (!isset($this->logLevels[$level])) {
            $level = 'info';
        }

        if ($this->logLevels[$level] < $this->minLogLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $caller = $this->getCallerInfo();

        $sanitizedMessage = $this->sanitize($message);
        $sanitizedContext = $this->sanitize($context);

        $contextString = '';
        if (!empty($sanitizedContext)) {
            $contextString = ' ' . json_encode($sanitizedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $logEntry = sprintf(
            '[%s] %s: %s%s [%s:%d]',
            $timestamp,
            strtoupper($level),
            $sanitizedMessage,
            $contextString,
            $caller['file'],
            $caller['line']
        );

        $this->writeToLog($logEntry);
    }

    private function sanitize($data)
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                if (in_array(strtolower($key), $this->sensitiveKeywords)) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = $this->sanitize($value);
                }
            }
            return $sanitized;
        } elseif (is_string($data)) {
            $sanitizedString = $data;
            foreach ($this->sensitivePatterns as $pattern => $replacement) {
                $sanitizedString = preg_replace($pattern, $replacement, $sanitizedString);
            }
            return $sanitizedString;
        } else {
            return $data;
        }
    }

    private function getCallerInfo(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $file = 'unknown';
        $line = 0;

        foreach ($trace as $frame) {
            if (isset($frame['class']) && strpos($frame['class'], 'Logger') !== false) {
                continue;
            }

            if (isset($frame['file'])) {
                $file = $frame['file'];
                $line = $frame['line'];
                break;
            }
        }

        return ['file' => $file, 'line' => $line];
    }

    private function writeToLog(string $logEntry): void
    {
        file_put_contents(
            $this->logFilePath,
            $logEntry . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
?>