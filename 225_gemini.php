<?php

class AppLogger
{
    private static ?AppLogger $instance = null;
    private string $logFilePath;
    private int $minLogLevel = 200;
    private array $logLevels = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    private function __construct(string $logFilePath, string $minLevel)
    {
        $this->logFilePath = $logFilePath;
        $this->setMinLogLevel($minLevel);
        $this->ensureLogDirectoryExists();
    }

    private function __clone() {}
    public function __wakeup() {}

    public static function getInstance(string $logFilePath = null, string $minLevel = null): AppLogger
    {
        if (self::$instance === null) {
            if ($logFilePath === null || empty($logFilePath)) {
                 $logFilePath = __DIR__ . '/../logs/app.log';
            }
            self::$instance = new self($logFilePath, $minLevel ?? 'info');
        } else {
            if ($minLevel !== null) {
                 self::$instance->setMinLogLevel($minLevel);
            }
        }
        return self::$instance;
    }

    public function setMinLogLevel(string $level): void
    {
        $level = strtolower($level);
        if (isset($this->logLevels[$level])) {
            $this->minLogLevel = $this->logLevels[$level];
        } else {
            $this->minLogLevel = $this->logLevels['info'];
        }
    }

    public function getMinLogLevelName(): string
    {
        foreach ($this->logLevels as $name => $level) {
            if ($level === $this->minLogLevel) {
                return $name;
            }
        }
        return 'unknown';
    }

    private function ensureLogDirectoryExists(): void
    {
        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
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
        $callerInfo = $this->getCallerInfo();
        $sanitizedMessage = $this->sanitizeMessage($message);
        $sanitizedContext = $this->sanitizeContext($context);

        $logEntry = sprintf(
            "%s [%s] %s %s%s\n",
            $timestamp,
            strtoupper($level),
            $callerInfo,
            $sanitizedMessage,
            empty($sanitizedContext) ? '' : ' ' . json_encode($sanitizedContext)
        );

        file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function sanitizeMessage(string $message): string
    {
        $message = preg_replace('/\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9]{2})[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})\b/x', '[REDACTED_CREDIT_CARD]', $message);

        $message = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $message);

        $message = preg_replace_callback(
            '/(password|passwd|pass|secret|api_key|api_token|token|auth_token|credit_card_number|ssn|social_security_number|security_code|cvv|dob|date_of_birth|full_name|address|email_address)\s*(=|:)\s*([\'"]?)([^\s\'"]+)\3/i',
            function ($matches) {
                return $matches[1] . $matches[2] . '[REDACTED_' . strtoupper(str_replace('_', '', $matches[1])) . ']';
            },
            $message
        );

        $sensitiveKeywords = [
            'password', 'passwd', 'credit card', 'credit_card', 'ssn', 'social security',
            'pin', 'cvv', 'security code', 'api key', 'auth token', 'token',
            'dob', 'date of birth', 'email', 'address', 'phone number', 'phone_number',
            'mobile number', 'mobile_number'
        ];
        foreach ($sensitiveKeywords as $keyword) {
            $message = preg_replace('/\b' . preg_quote($keyword, '/') . '\b/i', '[' . strtoupper(str_replace(' ', '_', $keyword)) . '_REDACTED]', $message);
        }

        return $message;
    }

    private function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            if (is_string($value)) {
                $sanitizedValue = $this->sanitizeMessage($value);
                $sanitized[$key] = $sanitizedValue;
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    private function getCallerInfo(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? null;

        if ($caller) {
            $file = $caller['file'] ?? 'unknown';
            $line = $caller['line'] ?? 'unknown';
            $function = $caller['function'] ?? 'unknown';
            $class = $caller['class'] ?? '';
            $type = $caller['type'] ?? '';

            $source = basename($file) . ':' . $line;
            if (!empty($class)) {
                $source .= ' ' . $class . $type . $function;
            } elseif (!empty($function)) {
                $source .= ' ' . $function;
            }
            return '[' . $source . ']';
        }
        return '[unknown_source]';
    }
}
?>