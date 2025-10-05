<?php

// Logger.php

class Logger
{
    private string $logFile;
    private int $minLogLevel;

    private const LOG_LEVELS = [
        'DEBUG'    => 100,
        'INFO'     => 200,
        'NOTICE'   => 250,
        'WARNING'  => 300,
        'ERROR'    => 400,
        'CRITICAL' => 500,
    ];

    private const SENSITIVE_KEYS = [
        'password', 'pwd', 'pass', 'secret', 'token', 'api_key', 'auth_token', 'bearer',
        'cc_number', 'credit_card', 'ssn', 'social_security_number', 'cvv', 'card_number',
    ];

    public function __construct(string $logFile, string $minLogLevel = 'INFO')
    {
        $this->logFile = $logFile;
        $this->setMinLogLevel($minLogLevel);
        $this->ensureLogFileDirectoryExists();
    }

    private function setMinLogLevel(string $level): void
    {
        $level = strtoupper($level);
        if (!isset(self::LOG_LEVELS[$level])) {
            throw new \InvalidArgumentException("Invalid log level: {$level}");
        }
        $this->minLogLevel = self::LOG_LEVELS[$level];
    }

    private function ensureLogFileDirectoryExists(): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }
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

    private function log(string $level, string $message, array $context = []): void
    {
        if (self::LOG_LEVELS[$level] < $this->minLogLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $source = $this->getCallerSource();

        $redactedMessage = $this->redactSensitiveData($message);
        $redactedContext = $this->redactSensitiveDataInArray($context);

        $logEntry = sprintf(
            "[%s] [%s] %s: %s %s\n",
            $timestamp,
            $level,
            $source,
            $redactedMessage,
            $redactedContext ? json_encode($redactedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function getCallerSource(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? $trace[1];

        $file = $caller['file'] ?? 'unknown_file';
        $line = $caller['line'] ?? 0;

        return basename($file) . ':' . $line;
    }

    private function redactSensitiveData(string $data): string
    {
        $redactedData = $data;

        // Credit Card Numbers (broad but common patterns)
        $ccPatterns = [
            '/(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9]{2})[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})/x',
            '/\b\d{4}[- ]?\d{4}[- ]?\d{4}[- ]?\d{4}\b/', // Generic 16-digit pattern
        ];
        foreach ($ccPatterns as $pattern) {
            $redactedData = preg_replace($pattern, '[REDACTED_CARD_NUMBER]', $redactedData);
        }

        // Passwords, tokens, API keys (key=value, "key": "value", or 'key': 'value' patterns)
        $sensitiveValuePatterns = [
            '/(password|pwd|pass|secret|token|api_key|auth_token|bearer)\s*=\s*([^\s&,;]+)/i' => '$1=[REDACTED_VALUE]',
            '/"(password|pwd|pass|secret|token|api_key|auth_token|bearer)":\s*"[^"]+"/i' => '"$1":"[REDACTED_VALUE]"',
            '/\'(password|pwd|pass|secret|token|api_key|auth_token|bearer)\':\s*\'[^\']+\'/i' => '\'$1\':\'[REDACTED_VALUE]\'',
        ];
        foreach ($sensitiveValuePatterns as $pattern => $replacement) {
            $redactedData = preg_replace($pattern, $replacement, $redactedData);
        }

        // Email addresses
        $redactedData = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $redactedData);

        // Social Security Numbers (XXX-XX-XXXX)
        $redactedData = preg_replace('/\b\d{3}[-.\s]?\d{2}[-.\s]?\d{4}\b/', '[REDACTED_SSN]', $redactedData);

        return $redactedData;
    }

    private function redactSensitiveDataInArray(array $data): array
    {
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);

            if (in_array($lowerKey, self::SENSITIVE_KEYS, true)) {
                if (is_string($value)) {
                    $data[$key] = '[REDACTED_BY_KEY]';
                } elseif (is_array($value)) {
                    // Even if it's an array, if the key itself is sensitive, redact the whole thing
                    $data[$key] = '[REDACTED_BY_KEY_ARRAY]';
                }
            } elseif (is_string($value)) {
                $data[$key] = $this->redactSensitiveData($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->redactSensitiveDataInArray($value);
            }
        }
        return $data;
    }
}
<?php

// app.php

require_once 'Logger.php';

$logger = new Logger(__DIR__ . '/logs/app.log', 'INFO');

$logger->debug('This is a debug message. It will NOT be logged as minLogLevel is INFO.', ['data' => 'secret_debug_info']);

$logger->info('User login successful.', ['user_id' => 12345, 'ip_address' => '192.168.1.1']);

$logger->notice('A new product has been added to the catalog.', ['product_id' => 'PROD-001', 'category' => 'Electronics']);

$logger->warning('API rate limit approaching. Current usage: 85%.', ['api_endpoint' => '/v1/users', 'requests_made' => 850]);

$logger->error('Failed to process order.', ['order_id' => 'ORD-001', 'reason' => 'Payment gateway error']);

$logger->critical('System crashed. Database connection lost!', ['server_ip' => '10.0.0.5', 'db_name' => 'production']);

$sensitiveData = [
    'username' => 'testuser',
    'password' => 'MySuperSecretPassword123!',
    'credit_card_number' => '4111-2222-3333-4444',
    'email_address' => 'sensitive.user@example.com',
    'social_security_number' => '987-65-4321',
    'api_token' => 'sk_live_XXXXXXXXXXXXXXXXXXXXX',
    'session_id' => 'regular_session_id_abc',
    'nested_data' => [
        'password_confirm' => 'AnotherSecretPassword!',
        'card_cvv' => '123',
    ],
    'raw_post_data' => 'user=admin&password=postpassword&token=some_token_value&data=regular_data',
    'json_payload' => '{"user":"test", "password":"json_password", "card_number":"5123456789012345"}',
    'message_with_email_and_cc' => 'User sent an email to client@example.net with card 4123456789012345.',
];

$logger->info('Attempting to log sensitive information payload:', $sensitiveData);

$logger->error('A user with email user@domain.com attempted login with password=failed_pass. Credit card number: 4123 4567 8901 2345.', [
    'request_uri' => '/login?user=test&password=query_pass',
    'token' => 'Bearer some_long_access_token_string_here'
]);

$debugLogger = new Logger(__DIR__ . '/logs/app_debug.log', 'DEBUG');
$debugLogger->debug('This debug message WILL be logged to the debug file.', ['timestamp' => time()]);
$debugLogger->info('This info message also goes to the debug file.');

echo "Logging demonstration complete. Check 'logs/app.log' and 'logs/app_debug.log' files.\n";
?>