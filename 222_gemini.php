<?php

class Logger
{
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const NOTICE = 'NOTICE';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';
    public const CRITICAL = 'CRITICAL';
    public const ALERT = 'ALERT';
    public const EMERGENCY = 'EMERGENCY';

    private static string $logFilePath = __DIR__ . '/app.log';
    private static string $minLogLevel = self::INFO;

    private static array $levelPriorities = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::NOTICE => 2,
        self::WARNING => 3,
        self::ERROR => 4,
        self::CRITICAL => 5,
        self::ALERT => 6,
        self::EMERGENCY => 7,
    ];

    private static array $sensitiveKeywords = [
        'password', 'pwd', 'pass', 'secret', 'token',
        'credit_card', 'cc_number', 'card_number', 'card_id', 'cvv', 'cvc',
        'ssn', 'social_security_number', 'id_number', 'drivers_license',
        'phone', 'phone_number', 'tel',
        'email', 'mail',
        'dob', 'date_of_birth',
        'address', 'street', 'city', 'zip', 'postcode',
        'account_number', 'bank_account', 'iban', 'sort_code'
    ];

    private static array $sensitivePatterns = [
        '/\b(?:\d[ -]*?){13,16}\b/',
        '/\b\d{3}[ -]?\d{2}[ -]?\d{4}\b/',
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/i',
    ];

    public static function setLogFilePath(string $path): void
    {
        self::$logFilePath = $path;
    }

    public static function setMinLogLevel(string $level): void
    {
        $level = strtoupper($level);
        if (isset(self::$levelPriorities[$level])) {
            self::$minLogLevel = $level;
        }
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log(self::DEBUG, $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::log(self::NOTICE, $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log(self::CRITICAL, $message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::log(self::ALERT, $message, $context);
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::log(self::EMERGENCY, $message, $context);
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $level = strtoupper($level);

        if (!self::shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $source = $context['source'] ?? self::inferSource();

        $redactedMessage = self::redactSensitiveData($message);
        $redactedContext = self::redactArray($context);

        $logEntry = sprintf(
            "[%s] %s: %s (Source: %s)%s%s",
            $timestamp,
            $level,
            $redactedMessage,
            $source,
            !empty($redactedContext) ? ' Context: ' . json_encode($redactedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
            PHP_EOL
        );

        self::writeLog($logEntry);
    }

    private static function inferSource(): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $sourceFrame = null;

        foreach ($backtrace as $i => $frame) {
            if ($i > 0 && !(isset($frame['class']) && str_starts_with($frame['class'], 'Logger'))) {
                $sourceFrame = $frame;
                break;
            }
        }

        if ($sourceFrame) {
            if (isset($sourceFrame['class'])) {
                $source = $sourceFrame['class'];
                if (isset($sourceFrame['function'])) {
                    $source .= '::' . $sourceFrame['function'];
                }
            } elseif (isset($sourceFrame['file'])) {
                $source = basename($sourceFrame['file']);
                if (isset($sourceFrame['line'])) {
                    $source .= ':' . $sourceFrame['line'];
                }
            }
        }

        return $source ?? 'UNKNOWN';
    }

    private static function shouldLog(string $level): bool
    {
        return (self::$levelPriorities[$level] ?? -1) >= (self::$levelPriorities[self::$minLogLevel] ?? -1);
    }

    private static function redactSensitiveData(string $data): string
    {
        $redactedData = $data;

        foreach (self::$sensitivePatterns as $pattern) {
            $redactedData = preg_replace($pattern, '[REDACTED]', $redactedData);
        }

        foreach (self::$sensitiveKeywords as $keyword) {
            $redactedData = preg_replace("/\b" . preg_quote($keyword, '/') . "[ -_]*[:=]?\s*[\w\d\-_@.\/]*\b/i", '[REDACTED]', $redactedData);
        }
        
        return $redactedData;
    }

    private static function redactArray(array $array): array
    {
        $redactedArray = [];
        foreach ($array as $key => $value) {
            $isKeySensitive = false;
            foreach (self::$sensitiveKeywords as $sk) {
                if (stripos($key, $sk) !== false) {
                    $isKeySensitive = true;
                    break;
                }
            }

            if (is_array($value)) {
                $redactedArray[$key] = self::redactArray($value);
            } elseif (is_string($value)) {
                if ($isKeySensitive) {
                    $redactedArray[$key] = '[REDACTED_VALUE]';
                } else {
                    $redactedValue = $value;
                    foreach (self::$sensitivePatterns as $pattern) {
                        if (preg_match($pattern, $value)) {
                            $redactedValue = '[REDACTED_VALUE]';
                            break;
                        }
                    }
                    $redactedArray[$key] = $redactedValue;
                }
            } else {
                $redactedArray[$key] = $value;
            }
        }
        return $redactedArray;
    }

    private static function writeLog(string $logEntry): void
    {
        file_put_contents(self::$logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>