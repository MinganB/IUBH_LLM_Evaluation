<?php

namespace App\Classes;

class Logger
{
    public const DEBUG = 100;
    public const INFO = 200;
    public const ERROR = 400;

    protected string $logFile;
    protected int $minLogLevel;
    protected array $sensitivePatterns = [];
    protected array $sensitiveKeys = [];

    public function __construct(string $logFilePath, int $minLogLevel = self::INFO)
    {
        $this->logFile = $logFilePath;
        $this->minLogLevel = $minLogLevel;

        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $this->sensitivePatterns = [
            '/(?:password|passwd|pwd)\s*[:=]\s*["\']?[a-zA-Z0-9!@#$%^&*()_+\-=\[\]{};:\'",.<>\/?~`]{6,}/i',
            '/(?:credit\s*card|cc\s*number|card\s*no)\s*[:=]?\s*(?:\d[ -]*?){13,16}/i',
            '/\bcvv\s*[:=]?\s*\d{3,4}\b/i',
            '/\bssn\s*[:=]?\s*\d{3}-\d{2}-\d{4}\b/i',
            '/\bemail\s*[:=]\s*[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}\b/i',
            '/\btoken\s*[:=]\s*[a-zA-Z0-9\-_.]+/i',
        ];

        $this->sensitiveKeys = [
            'password', 'passwd', 'pwd', 'secret', 'api_key', 'token',
            'credit_card_number', 'cc_number', 'card_number', 'ssn', 'cvv', 'pin',
            'email', 'firstName', 'lastName', 'address', 'phone', 'dateOfBirth',
        ];
    }

    protected function getLogLevelString(int $level): string
    {
        return match ($level) {
            self::DEBUG => 'DEBUG',
            self::INFO => 'INFO',
            self::ERROR => 'ERROR',
            default => 'UNKNOWN',
        };
    }

    protected function redact(string $data): string
    {
        foreach ($this->sensitivePatterns as $pattern) {
            $data = preg_replace($pattern, '[REDACTED]', $data);
        }
        return $data;
    }

    protected function redactContext(array $context): array
    {
        foreach ($context as $key => &$value) {
            if (in_array(strtolower($key), $this->sensitiveKeys)) {
                $value = '[REDACTED]';
            } elseif (is_string($value)) {
                $value = $this->redact($value);
            } elseif (is_array($value)) {
                $value = $this->redactContext($value);
            }
        }
        return $context;
    }

    protected function write(int $level, string $message, array $context = [], string $source = ''): void
    {
        if ($level < $this->minLogLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelString = $this->getLogLevelString($level);

        if (empty($source)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            if (isset($trace[2])) {
                if (isset($trace[2]['class'])) {
                    $source = $trace[2]['class'] . '::' . $trace[2]['function'];
                } elseif (isset($trace[2]['file'])) {
                    $source = basename($trace[2]['file']) . ':' . $trace[2]['line'];
                }
            } else {
                $source = 'UnknownSource';
            }
        }

        $redactedMessage = $this->redact($message);
        $redactedContext = $this->redactContext($context);

        $logEntry = sprintf(
            "[%s] [%s] [%s] %s %s\n",
            $timestamp,
            $levelString,
            $source,
            $redactedMessage,
            !empty($redactedContext) ? json_encode($redactedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function debug(string $message, array $context = [], string $source = ''): void
    {
        $this->write(self::DEBUG, $message, $context, $source);
    }

    public function info(string $message, array $context = [], string $source = ''): void
    {
        $this->write(self::INFO, $message, $context, $source);
    }

    public function error(string $message, array $context = [], string $source = ''): void
    {
        $this->write(self::ERROR, $message, $context, $source);
    }
}

<?php

require_once __DIR__ . '/../classes/Logger.php';

use App\Classes\Logger;

$logFilePath = __DIR__ . '/../logs/app.log';

$logger = new Logger($logFilePath, Logger::DEBUG);

$logger->info('User logged in successfully.', ['userId' => 123, 'ipAddress' => '192.168.1.100'], 'AuthHandler::login');

$logger->debug('Fetching inventory item details.', ['itemId' => 456, 'status' => 'pending'], 'InventoryService::getItem');

$logger->error('Failed to update inventory quantity.', ['itemId' => 789, 'requestedQty' => 5, 'availableQty' => 2, 'error' => 'Insufficient stock'], 'InventoryHandler::updateItemQuantity');

$logger->info('New product added to catalog.', [
    'productId' => 101,
    'productName' => 'Secure Widget',
    'secretKey' => 'very_secret_key_123',
    'adminUser' => 'john.doe@example.com',
    'password' => 'MyStrongPassword123!',
    'billingInfo' => [
        'credit_card_number' => '4111-1111-1111-1111',
        'cvv' => '123'
    ]
], 'ProductHandler::addProduct');

$logger->error('Payment processing failed for order #500. User provided card_number: 5555-4444-3333-2222 and CVV: 789. Also, their email: user@sensitive.com.',
    ['orderId' => 500, 'gateway' => 'Stripe'], 'PaymentProcessor::process');

$sensitiveMessage = "Debugging user 'JaneDoe' with password 'password123' and token 'ABC-DEF-GHI'.";
$sensitiveContext = [
    'userId' => 321,
    'username' => 'JaneDoe',
    'password' => 'another-password',
    'apiKey' => 'XYZ-123-ABC',
    'paymentDetails' => 'CC#: 1234-5678-9012-3456 CVV: 987'
];
$logger->debug($sensitiveMessage, $sensitiveContext, 'UserAuthService::debugSession');

echo "Logging demonstration complete. Check 'logs/app.log' for output.\n";
?>