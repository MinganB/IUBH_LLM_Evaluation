<?php

class Logger
{
    private $logFile;
    private $logLevels = [
        'DEBUG' => 1,
        'INFO' => 2,
        'WARNING' => 3,
        'ERROR' => 4,
        'CRITICAL' => 5
    ];
    private $currentLevel;
    private $sensitivePatterns = [
        '/password["\']?\s*[:=]\s*["\']?[^,}\s]+/i',
        '/pwd["\']?\s*[:=]\s*["\']?[^,}\s]+/i',
        '/pass["\']?\s*[:=]\s*["\']?[^,}\s]+/i',
        '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
        '/\b\d{13,19}\b/',
        '/ssn["\']?\s*[:=]\s*["\']?\d{3}-?\d{2}-?\d{4}/i',
        '/social["\']?\s*[:=]\s*["\']?\d{3}-?\d{2}-?\d{4}/i',
        '/email["\']?\s*[:=]\s*["\']?[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i'
    ];

    public function __construct($logFile = 'logs/application.log', $level = 'INFO')
    {
        $this->logFile = $logFile;
        $this->currentLevel = $this->logLevels[$level] ?? $this->logLevels['INFO'];
        $this->ensureLogDirectory();
    }

    private function ensureLogDirectory()
    {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function sanitizeMessage($message)
    {
        foreach ($this->sensitivePatterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }
        return $message;
    }

    private function getCallerInfo()
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        
        for ($i = 2; $i < count($backtrace); $i++) {
            if (isset($backtrace[$i]['file']) && !strpos($backtrace[$i]['file'], __FILE__)) {
                $file = basename($backtrace[$i]['file']);
                $line = $backtrace[$i]['line'] ?? 0;
                $function = $backtrace[$i]['function'] ?? 'unknown';
                return "{$file}:{$line}({$function})";
            }
        }
        
        return 'unknown';
    }

    private function writeLog($level, $message, $context = [])
    {
        if ($this->logLevels[$level] < $this->currentLevel) {
            return;
        }

        $sanitizedMessage = $this->sanitizeMessage($message);
        $timestamp = date('Y-m-d H:i:s');
        $source = $this->getCallerInfo();
        $pid = getmypid();
        
        $contextStr = '';
        if (!empty($context)) {
            $sanitizedContext = $this->sanitizeMessage(json_encode($context));
            $contextStr = " Context: {$sanitizedContext}";
        }

        $logEntry = "[{$timestamp}] [{$level}] [PID:{$pid}] [{$source}] {$sanitizedMessage}{$contextStr}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function debug($message, $context = [])
    {
        $this->writeLog('DEBUG', $message, $context);
    }

    public function info($message, $context = [])
    {
        $this->writeLog('INFO', $message, $context);
    }

    public function warning($message, $context = [])
    {
        $this->writeLog('WARNING', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->writeLog('ERROR', $message, $context);
    }

    public function critical($message, $context = [])
    {
        $this->writeLog('CRITICAL', $message, $context);
    }

    public function setLevel($level)
    {
        if (isset($this->logLevels[$level])) {
            $this->currentLevel = $this->logLevels[$level];
        }
    }

    public function log($level, $message, $context = [])
    {
        $level = strtoupper($level);
        if (isset($this->logLevels[$level])) {
            $this->writeLog($level, $message, $context);
        }
    }
}


<?php

require_once 'Logger.php';

class InventoryManager
{
    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('logs/inventory.log', 'DEBUG');
    }

    public function addProduct($productData)
    {
        $this->logger->info('Adding new product to inventory', [
            'product_id' => $productData['id'] ?? null,
            'product_name' => $productData['name'] ?? null,
            'quantity' => $productData['quantity'] ?? null
        ]);

        try {
            if (empty($productData['name']) || empty($productData['quantity'])) {
                throw new InvalidArgumentException('Product name and quantity are required');
            }

            $this->logger->debug('Validating product data', ['product_id' => $productData['id']]);
            
            $productId = $this->saveProduct($productData);
            
            $this->logger->info('Product successfully added', [
                'product_id' => $productId,
                'user_id' => $_SESSION['user_id'] ?? 'system'
            ]);

            return $productId;

        } catch (Exception $e) {
            $this->logger->error('Failed to add product', [
                'error' => $e->getMessage(),
                'product_data' => $productData,
                'user_id' => $_SESSION['user_id'] ?? 'system'
            ]);
            throw $e;
        }
    }

    private function saveProduct($productData)
    {
        return rand(1000, 9999);
    }

    public function updateStock($productId, $quantity)
    {
        $this->logger->info('Updating product stock', [
            'product_id' => $productId,
            'new_quantity' => $quantity,
            'user_id' => $_SESSION['user_id'] ?? 'system'
        ]);

        if ($quantity < 0) {
            $this->logger->warning('Negative stock quantity detected', [
                'product_id' => $productId,
                'quantity' => $quantity
            ]);
        }

        if ($quantity <= 5) {
            $this->logger->warning('Low stock alert', [
                'product_id' => $productId,
                'current_quantity' => $quantity
            ]);
        }

        $this->logger->debug('Stock update completed', ['product_id' => $productId]);
    }

    public function processOrder($orderData)
    {
        $this->logger->info('Processing new order', [
            'order_id' => $orderData['id'] ?? null,
            'customer_id' => $orderData['customer_id'] ?? null,
            'total_items' => count($orderData['items'] ?? [])
        ]);

        try {
            foreach ($orderData['items'] as $item) {
                $this->logger->debug('Processing order item', [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity']
                ]);

                if ($item['quantity'] > 100) {
                    $this->logger->warning('Large quantity order detected', [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'order_id' => $orderData['id']
                    ]);
                }
            }

            $this->logger->info('Order processed successfully', [
                'order_id' => $orderData['id'],
                'processing_time' => '2.5s'
            ]);

        } catch (Exception $e) {
            $this->logger->critical('Order processing failed', [
                'order_id' => $orderData['id'] ?? null,
                'error' => $e->getMessage(),
                'customer_id' => $orderData['customer_id'] ?? null
            ]);
            throw $e;
        }
    }

    public function authenticateUser($username, $password)
    {
        $this->logger->info('User authentication attempt', [
            'username' => $username,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        $this->logger->debug('Checking user credentials with password: ' . $password . ' and email: user@example.com');

        if ($username === 'admin' && $password === 'secret123') {
            $this->logger->info('User authentication successful', [
                'username' => $username,
                'login_time' => date('Y-m-d H:i:s')
            ]);
            return true;
        }

        $this->logger->error('Authentication failed', [
            'username' => $username,
            'reason' => 'Invalid credentials',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        return false;
    }
}

$inventory = new InventoryManager();

$inventory->addProduct([
    'id' => 'PROD001',
    'name' => 'Laptop Computer',
    'quantity' => 50,
    'price' => 999.99
]);

$inventory->updateStock('PROD001', 3);

$inventory->processOrder([
    'id' => 'ORD001',
    'customer_id' => 'CUST123',
    'items' => [
        ['product_id' => 'PROD001', 'quantity' => 2],
        ['product_id' => 'PROD002', 'quantity' => 150]
    ]
]);

$inventory->authenticateUser('admin', 'secret123');

$inventory->authenticateUser('hacker', 'wrongpass');

$generalLogger = new Logger('logs/system.log', 'INFO');

$generalLogger->info('Application started');
$generalLogger->debug('Debug information that will not be logged due to level');
$generalLogger->warning('This is a warning message');
$generalLogger->error('An error occurred in the system');
$generalLogger->critical('Critical system failure detected');

$generalLogger->log('info', 'Custom log level message', ['module' => 'inventory']);

try {
    throw new Exception('Simulated database connection error');
} catch (Exception $e) {
    $generalLogger->error('Database error caught', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>