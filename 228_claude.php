<?php

class Logger
{
    private $logFile;
    private $sensitivePatterns;
    
    public function __construct($logFile = 'application.log')
    {
        $this->logFile = $logFile;
        $this->sensitivePatterns = [
            '/password[=:\s]*[^\s&\n\r]*/i',
            '/pass[=:\s]*[^\s&\n\r]*/i',
            '/pwd[=:\s]*[^\s&\n\r]*/i',
            '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/',
            '/\b\d{13,19}\b/',
            '/\b\d{3}-\d{2}-\d{4}\b/',
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            '/api[_-]?key[=:\s]*[^\s&\n\r]*/i',
            '/token[=:\s]*[^\s&\n\r]*/i',
            '/secret[=:\s]*[^\s&\n\r]*/i',
            '/authorization[=:\s]*[^\s&\n\r]*/i',
            '/bearer[=:\s]*[^\s&\n\r]*/i'
        ];
    }
    
    private function sanitizeMessage($message)
    {
        foreach ($this->sensitivePatterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }
        return $message;
    }
    
    private function writeLog($level, $message, $source = null)
    {
        $sanitizedMessage = $this->sanitizeMessage($message);
        $timestamp = date('Y-m-d H:i:s');
        
        if ($source === null) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $caller = isset($backtrace[2]) ? $backtrace[2] : $backtrace[1];
            $source = (isset($caller['class']) ? $caller['class'] . '::' : '') . 
                     (isset($caller['function']) ? $caller['function'] : 'unknown') . 
                     (isset($caller['line']) ? ':' . $caller['line'] : '');
        }
        
        $logEntry = "[{$timestamp}] [{$level}] [{$source}] {$sanitizedMessage}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function info($message, $source = null)
    {
        $this->writeLog('INFO', $message, $source);
    }
    
    public function error($message, $source = null)
    {
        $this->writeLog('ERROR', $message, $source);
    }
    
    public function debug($message, $source = null)
    {
        $this->writeLog('DEBUG', $message, $source);
    }
    
    public function warning($message, $source = null)
    {
        $this->writeLog('WARNING', $message, $source);
    }
    
    public function critical($message, $source = null)
    {
        $this->writeLog('CRITICAL', $message, $source);
    }
    
    public function notice($message, $source = null)
    {
        $this->writeLog('NOTICE', $message, $source);
    }
}


<?php

require_once 'Logger.php';

class UserService
{
    private $logger;
    
    public function __construct()
    {
        $this->logger = new Logger('app.log');
    }
    
    public function authenticateUser($username, $password)
    {
        $this->logger->info("User authentication attempt for username: {$username}");
        
        if (empty($username) || empty($password)) {
            $this->logger->error("Authentication failed: missing credentials for user {$username}");
            return false;
        }
        
        if ($this->validateCredentials($username, $password)) {
            $this->logger->info("User {$username} successfully authenticated");
            return true;
        } else {
            $this->logger->warning("Authentication failed for user {$username}");
            return false;
        }
    }
    
    private function validateCredentials($username, $password)
    {
        $this->logger->debug("Validating credentials for user: {$username} with password: {$password}");
        return $username === 'admin' && $password === 'secret123';
    }
    
    public function processPayment($cardNumber, $amount)
    {
        $this->logger->info("Processing payment for amount: \${$amount}");
        $this->logger->debug("Payment details - Card: {$cardNumber}, Amount: {$amount}");
        
        if ($amount <= 0) {
            $this->logger->error("Invalid payment amount: {$amount}");
            return false;
        }
        
        if ($this->validateCard($cardNumber)) {
            $this->logger->info("Payment processed successfully for amount: \${$amount}");
            return true;
        } else {
            $this->logger->critical("Payment processing failed for card: {$cardNumber}");
            return false;
        }
    }
    
    private function validateCard($cardNumber)
    {
        $this->logger->debug("Validating credit card: {$cardNumber}");
        return strlen(str_replace([' ', '-'], '', $cardNumber)) === 16;
    }
}

class DatabaseConnection
{
    private $logger;
    
    public function __construct()
    {
        $this->logger = new Logger('app.log');
    }
    
    public function connect($host, $username, $password, $database)
    {
        $this->logger->info("Attempting database connection to {$host}");
        $this->logger->debug("Connection params - Host: {$host}, User: {$username}, Pass: {$password}, DB: {$database}");
        
        try {
            $this->logger->info("Database connection established successfully");
            return true;
        } catch (Exception $e) {
            $this->logger->critical("Database connection failed: " . $e->getMessage());
            return false;
        }
    }
}

$userService = new UserService();
$dbConnection = new DatabaseConnection();

$userService->authenticateUser('john_doe', 'mypassword123');
$userService->authenticateUser('admin', 'secret123');
$userService->authenticateUser('', '');

$userService->processPayment('4532-1234-5678-9012', 99.99);
$userService->processPayment('1234567890123456', -50);

$dbConnection->connect('localhost', 'db_user', 'db_password_secret', 'app_database');

$logger = new Logger('general.log');

$logger->info('Application started successfully');
$logger->debug('Debug information with sensitive data: API_KEY=sk_live_123456789 and password=hidden');
$logger->warning('Low disk space detected');
$logger->error('Failed to load configuration file');
$logger->critical('System is running out of memory');
$logger->notice('Scheduled maintenance will begin in 1 hour');

$logger->info('User email: user@example.com should be redacted');
$logger->debug('SSN: 123-45-6789 should not appear in logs');
$logger->error('Authorization: Bearer abc123token should be hidden');
?>