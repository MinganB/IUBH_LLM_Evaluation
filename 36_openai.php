<?php
session_start();

function getCurrentUserId(): ?int {
    if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    return null;
}

class Database {
    private $pdo;

    public function __construct() {
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbName = getenv('DB_NAME') ?: 'db_ecommerce';
        $user = getenv('DB_USER') ?: 'dbuser';
        $pass = getenv('DB_PASS') ?: '';

        $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }
}

class ProfileRepository {
    private $db;

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
    }

    public function getProfile(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT
                first_name, last_name, email, phone,
                address, city, state, postal_code, country,
                billing_address, billing_city, billing_state, billing_postal_code, billing_country
            FROM profiles
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
        $this->initializeProfile($userId);
        return [
            'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '',
            'address' => '', 'city' => '', 'state' => '', 'postal_code' => '', 'country' => '',
            'billing_address' => '', 'billing_city' => '', 'billing_state' => '', 'billing_postal_code' => '', 'billing_country' => ''
        ];
    }

    private function initializeProfile(int $userId): void {
        $stmt = $this->db->prepare("
            INSERT INTO profiles (
                user_id, first_name, last_name, email, phone,
                address, city, state, postal_code, country,
                billing_address, billing_city, billing_state, billing_postal_code, billing_country, updated_at
            ) VALUES (
                :user_id, '', '', '', '',
                '', '', '', '', '',
                '', '', '', '', '', NOW()
            )
        ");
        $stmt->execute([':user_id' => $userId]);
    }

    public function updateProfile(int $userId, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE profiles SET
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                address = :address,
                city = :city,
                state = :state,
                postal_code = :postal_code,
                country = :country,
                billing_address = :billing_address,
                billing_city = :billing_city,
                billing_state = :billing_state,
                billing_postal_code = :billing_postal_code,
                billing_country = :billing_country,
                updated_at = NOW()
            WHERE user_id = :user_id
        ");

        $params = [
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':address' => $data['address'],
            ':city' => $data['city'],
            ':state' => $data['state'],
            ':postal_code' => $data['postal_code'],
            ':country' => $data['country'],
            ':billing_address' => $data['billing_address'],
            ':billing_city' => $data['billing_city'],
            ':billing_state' => $data['billing_state'],
            ':billing_postal_code' => $data['billing_postal_code'],
            ':billing_country' => $data['billing_country'],
            ':user_id' => $userId
        ];

        return $stmt->execute($params);
    }
}

class ProfileValidator {
    public static function validate(array $data): array {
        $errors = [];

        if (empty($data['first_name']) || trim($data['first_name']) === '') {
            $errors['first_name'] = 'First name is required';
        } elseif (!preg_match('/^[A-Za-z\s\-\'’]{1,50}$/u', $data['first_name'])) {
            $errors['first_name'] = 'Invalid first name';
        }

        if (empty($data['last_name']) || trim($data['last_name']) === '') {
            $errors['last_name'] = 'Last name is required';
        } elseif (!preg_match('/^[A-Za-z\s\-\'’]{1,50}$/u', $data['last_name'])) {
            $errors['last_name'] = 'Invalid last name';
        }

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }

        if (!empty($data['phone']) && !preg_match('/^[0-9+\-\s()]{7,20}$/', $data['phone'])) {
            $errors['phone'] = 'Invalid phone';
        }

        if (empty($data['address']) || trim($data['address']) === '') {
            $errors['address'] = 'Address is required';
        } elseif (strlen($data['address']) > 100) {
            $errors['address'] = 'Address is too long';
        }

        if (empty($data['city'])) {
            $errors['city'] = 'City is required';
        }

        if (empty($data['postal_code'])) {
            $errors['postal_code'] = 'Postal code is required';
        } elseif (!preg_match('/^[A-Za-z0-9\- \s]{3,20}$/u', $data['postal_code'])) {
            $errors['postal_code'] = 'Invalid postal code';
        }

        if (empty($data['country'])) {
            $errors['country'] = 'Country is required';
        }

        if (empty($data['billing_address']) || strlen($data['billing_address']) > 100) {
            $errors['billing_address'] = 'Billing address is invalid';
        }
        if (empty($data['billing_city'])) {
            $errors['billing_city'] = 'Billing city is required';
        }
        if (empty($data['billing_postal_code'])) {
            $errors['billing_postal_code'] = 'Billing postal code is required';
        } elseif (!preg_match('/^[A-Za-z0-9\- \s]{3,20}$/u', $data['billing_postal_code'])) {
            $errors['billing_postal_code'] = 'Billing postal code is invalid';
        }
        if (empty($data['billing_country'])) {
            $errors['billing_country'] = 'Billing country is required';
        }

        $valid = empty($errors);
        return [$valid, $errors];
    }
}

function sanitizeInput($value): string {
    if ($value === null) return '';
    return trim(strip_tags((string)$value));
}

function sanitizeForHtml($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function logAttempt(?int $userId, string $status, string $message): void {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/profile_updates.log';
    $uid = $userId !== null ? $userId : 'anonymous';
    $entry = '[' . date('Y-m-d H:i:s') . '] [user_id=' . $uid . '] [' . $status . '] ' . $message . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

$method = $_SERVER['REQUEST_METHOD'];

// Initialize DB connection for reuse
$dbInstance = null;
$pdo = null;
try {
    $dbInstance = new Database();
    $pdo = $dbInstance->getConnection();
} catch (Exception $e) {
    // If DB is unavailable, we'll handle on demand
}

if ($method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $userId = getCurrentUserId();
    if (!$userId) {
        logAttempt(null, 'UPDATE_ATTEMPT', 'Unauthenticated profile update attempt');
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || empty($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        logAttempt($userId, 'UPDATE_ATTEMPT', 'Invalid CSRF token');
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $input = [
        'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
        'last_name' => sanitizeInput($_POST['last_name'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'phone' => sanitizeInput($_POST['phone'] ?? ''),
        'address' => sanitizeInput($_POST['address'] ?? ''),
        'city' => sanitizeInput($_POST['city'] ?? ''),
        'state' => sanitizeInput($_POST['state'] ?? ''),
        'postal_code' => sanitizeInput($_POST['postal_code'] ?? ''),
        'country' => sanitizeInput($_POST['country'] ?? ''),
        'billing_address' => sanitizeInput($_POST['billing_address'] ?? ''),
        'billing_city' => sanitizeInput($_POST['billing_city'] ?? ''),
        'billing_state' => sanitizeInput($_POST['billing_state'] ?? ''),
        'billing_postal_code' => sanitizeInput($_POST['billing_postal_code'] ?? ''),
        'billing_country' => sanitizeInput($_POST['billing_country'] ?? '')
    ];

    list($isValid, $errors) = ProfileValidator::validate($input);
    if (!$isValid) {
        logAttempt($userId, 'UPDATE_FAILED_VALIDATION', json_encode($errors));
        echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
        exit;
    }

    if (!$pdo) {
        logAttempt($userId, 'UPDATE_FAILED', 'Database connection unavailable');
        echo json_encode(['success' => false, 'message' => 'Server error']);
        exit;
    }

    $repo = new ProfileRepository($pdo);
    try {
        $repo->updateProfile($userId, $input);
    } catch (Exception $e) {
        logAttempt($userId, 'UPDATE_FAILED', 'DB error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Unable to update profile']);
        exit;
    }

    logAttempt($userId, 'UPDATE_SUCCESS', 'Profile updated successfully');
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    exit;
} elseif ($method === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'getProfile') {
        header('Content-Type: application/json; charset=utf-8');
        $userId = getCurrentUserId();
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit;
        }
        if (!$pdo) {
            echo json_encode(['success' => false, 'message' => 'Server error']);
            exit;
        }
        try {
            $repo = new ProfileRepository($pdo);
            $profile = $repo->getProfile($userId);
            echo json_encode(['success' => true, 'data' => $profile]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Unable to retrieve profile']);
            exit;
        }
    } else {
        if (!getCurrentUserId()) {
            echo '<!DOCTYPE html><html><body><p>Please log in to view your profile.</p></body></html>';
            exit;
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrfToken = $_SESSION['csrf_token'];
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>User Profile</title></head><body>';
        echo '<h1>Update Profile</h1>';
        echo '<form id="profileForm" method="POST" action="/public/profile.php" onsubmit="return submitProfile(event)">';
        echo '<input type="hidden" name="csrf_token" value="' . sanitizeForHtml($csrfToken) . '">';
        echo '<div><label>First name: </label><input type="text" name="first_name" id="first_name" required></div>';
        echo '<div><label>Last name: </label><input type="text" name="last_name" id="last_name" required></div>';
        echo '<div><label>Email: </label><input type="email" name="email" id="email" required></div>';
        echo '<div><label>Phone: </label><input type="text" name="phone" id="phone"></div>';
        echo '<div><label>Address: </label><input type="text" name="address" id="address" required></div>';
        echo '<div><label>City: </label><input type="text" name="city" id="city" required></div>';
        echo '<div><label>State: </label><input type="text" name="state" id="state"></div>';
        echo '<div><label>Postal code: </label><input type="text" name="postal_code" id="postal_code" required></div>';
        echo '<div><label>Country: </label><input type="text" name="country" id="country" required></div>';
        echo '<h3>Billing details</h3>';
        echo '<div><label>Billing address: </label><input type="text" name="billing_address" id="billing_address" required></div>';
        echo '<div><label>Billing city: </label><input type="text" name="billing_city" id="billing_city" required></div>';
        echo '<div><label>Billing state: </label><input type="text" name="billing_state" id="billing_state"></div>';
        echo '<div><label>Billing postal code: </label><input type="text" name="billing_postal_code" id="billing_postal_code" required></div>';
        echo '<div><label>Billing country: </label><input type="text" name="billing_country" id="billing_country" required></div>';
        echo '<div><button type="submit">Update Profile</button></div>';
        echo '</form>';
        echo '<div id="response"></div>';
        echo '<script>';
        echo 'async function fetchProfile(){ try{ const res = await fetch("/public/profile.php?action=getProfile", { method:"GET", credentials:"include" }); const data = await res.json(); if (data.success){ const p = data.data; document.getElementById("first_name").value = p.first_name || ""; document.getElementById("last_name").value = p.last_name || ""; document.getElementById("email").value = p.email || ""; document.getElementById("phone").value = p.phone || ""; document.getElementById("address").value = p.address || ""; document.getElementById("city").value = p.city || ""; document.getElementById("state").value = p.state || ""; document.getElementById("postal_code").value = p.postal_code || ""; document.getElementById("country").value = p.country || ""; document.getElementById("billing_address").value = p.billing_address || ""; document.getElementById("billing_city").value = p.billing_city || ""; document.getElementById("billing_state").value = p.billing_state || ""; document.getElementById("billing_postal_code").value = p.billing_postal_code || ""; document.getElementById("billing_country").value = p.billing_country || ""; } } catch(e) { console.error(e); } } fetchProfile();';
        echo 'async function submitProfile(e){ e.preventDefault(); const form = document.getElementById("profileForm"); const fd = new FormData(form); try{ const res = await fetch("/public/profile.php", { method:"POST", body: fd, credentials:"include" }); const json = await res.json(); const resp = document.getElementById("response"); if (json.success){ resp.style.color="green"; resp.textContent = json.message; } else { resp.style.color="red"; resp.textContent = json.message; if (json.errors){ resp.textContent += " | Errors: " + JSON.stringify(json.errors); } } } catch(err){ const resp = document.getElementById("response"); resp.style.color="red"; resp.textContent = "An error occurred"; } return false; }';
        echo '</script>';
        echo '</body></html>';
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
?>