<?php
// config/config.php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_ecommerce');
define('DB_USER', 'ecommerce_user');
define('DB_PASS', 'your_secure_password'); // !!! CHANGE THIS IN PRODUCTION !!!

// Log file path. This should ideally be outside the web-accessible directory.
// For this example, it's assumed a 'logs' directory exists at the same level as 'config', 'classes', 'handlers', 'public'.
define('LOG_FILE', __DIR__ . '/../logs/profile_updates.log');


<?php
// classes/Database.php

require_once __DIR__ . '/../config/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new Exception('Could not connect to the database.');
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }
}


<?php
// classes/UserProfile.php

require_once __DIR__ . '/Database.php';

class UserProfile {
    private PDO $db;

    public function __construct(Database $database) {
        $this->db = $database->getConnection();
    }

    public function getUserProfile(int $userId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM profiles WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $profile = $stmt->fetch();
        return $profile !== false ? $profile : null;
    }

    public function upsertUserProfile(int $userId, array $data): bool {
        $query = "
            INSERT INTO profiles (
                user_id, first_name, last_name, email, phone_number,
                address_street, address_city, address_state, address_zip, address_country,
                billing_street, billing_city, billing_state, billing_zip, billing_country,
                created_at, updated_at
            ) VALUES (
                :user_id, :first_name, :last_name, :email, :phone_number,
                :address_street, :address_city, :address_state, :address_zip, :address_country,
                :billing_street, :billing_city, :billing_state, :billing_zip, :billing_country,
                NOW(), NOW()
            ) ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                email = VALUES(email),
                phone_number = VALUES(phone_number),
                address_street = VALUES(address_street),
                address_city = VALUES(address_city),
                address_state = VALUES(address_state),
                address_zip = VALUES(address_zip),
                address_country = VALUES(address_country),
                billing_street = VALUES(billing_street),
                billing_city = VALUES(billing_city),
                billing_state = VALUES(billing_state),
                billing_zip = VALUES(billing_zip),
                billing_country = VALUES(billing_country),
                updated_at = NOW()
        ";

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':first_name', $data['first_name']);
            $stmt->bindParam(':last_name', $data['last_name']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':phone_number', $data['phone_number']);
            $stmt->bindParam(':address_street', $data['address_street']);
            $stmt->bindParam(':address_city', $data['address_city']);
            $stmt->bindParam(':address_state', $data['address_state']);
            $stmt->bindParam(':address_zip', $data['address_zip']);
            $stmt->bindParam(':address_country', $data['address_country']);
            $stmt->bindParam(':billing_street', $data['billing_street']);
            $stmt->bindParam(':billing_city', $data['billing_city']);
            $stmt->bindParam(':billing_state', $data['billing_state']);
            $stmt->bindParam(':billing_zip', $data['billing_zip']);
            $stmt->bindParam(':billing_country', $data['billing_country']);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("UserProfile upsert error for user_id {$userId}: " . $e->getMessage());
            return false;
        }
    }
}


<?php
// handlers/update_profile.php

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/UserProfile.php';
require_once __DIR__ . '/../config/config.php';

function log_profile_update_attempt(int $userId, bool $success, string $message) {
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILURE';
    $logEntry = "[{$timestamp}] User ID: {$userId}, Status: {$status}, Message: {$message}\n";
    error_log($logEntry, 3, LOG_FILE);
}

function send_json_response(bool $success, string $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

if (!isset($_SESSION['user_id']) || !is_int($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    log_profile_update_attempt(0, false, 'Unauthorized attempt: User ID not found in session.');
    send_json_response(false, 'Authentication required.');
}

$userId = $_SESSION['user_id'];
$formData = $_POST;
$errors = [];

$requiredFields = [
    'first_name', 'last_name', 'email',
    'address_street', 'address_city', 'address_state', 'address_zip', 'address_country',
    'billing_street', 'billing_city', 'billing_state', 'billing_zip', 'billing_country'
];

foreach ($requiredFields as $field) {
    if (empty($formData[$field])) {
        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
    }
    $formData[$field] = htmlspecialchars($formData[$field] ?? '', ENT_QUOTES, 'UTF-8');
}

if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format.';
}

$formData['phone_number'] = htmlspecialchars($formData['phone_number'] ?? '', ENT_QUOTES, 'UTF-8');
if (!empty($formData['phone_number']) && !preg_match('/^\+?\d{1,4}?[-.\s]?\(?\d{1,3}?\)?[-.\s]?\d{1,4}[-.\s]?\d{1,4}[-.\s]?\d{1,9}$/', $formData['phone_number'])) {
    $errors[] = 'Invalid phone number format.';
}

if (!empty($errors)) {
    log_profile_update_attempt($userId, false, 'Validation failed: ' . implode(', ', $errors));
    send_json_response(false, 'Validation failed: ' . implode(' ', $errors));
}

try {
    $database = Database::getInstance();
    $userProfile = new UserProfile($database);

    $updateResult = $userProfile->upsertUserProfile($userId, [
        'first_name' => $formData['first_name'],
        'last_name' => $formData['last_name'],
        'email' => $formData['email'],
        'phone_number' => $formData['phone_number'],
        'address_street' => $formData['address_street'],
        'address_city' => $formData['address_city'],
        'address_state' => $formData['address_state'],
        'address_zip' => $formData['address_zip'],
        'address_country' => $formData['address_country'],
        'billing_street' => $formData['billing_street'],
        'billing_city' => $formData['billing_city'],
        'billing_state' => $formData['billing_state'],
        'billing_zip' => $formData['billing_zip'],
        'billing_country' => $formData['billing_country'],
    ]);

    if ($updateResult) {
        log_profile_update_attempt($userId, true, 'Profile updated successfully.');
        send_json_response(true, 'Profile updated successfully.');
    } else {
        log_profile_update_attempt($userId, false, 'Database upsert failed for user, no rows affected or PDO error (check logs for details).');
        send_json_response(false, 'Failed to update profile. Please try again.');
    }

} catch (Exception $e) {
    log_profile_update_attempt($userId, false, 'Internal server error: ' . $e->getMessage());
    send_json_response(false, 'An internal server error occurred. Please try again later.');
}


<?php
// public/profile.php

session_start();
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/UserProfile.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // MOCK USER ID: In a production environment, this would come from a secure authentication process.
}
$userId = $_SESSION['user_id'];

$profileData = [];
if ($userId) {
    try {
        $database = Database::getInstance();
        $userProfile = new UserProfile($database);
        $profileData = $userProfile->getUserProfile($userId);
    } catch (Exception $e) {
        error_log('Error fetching user profile for ID ' . $userId . ': ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
</head>
<body>
    <h1>User Profile</h1>
    <form id="profileForm" method="POST" action="/handlers/update_profile.php">
        <section>
            <h2>Personal Information</h2>
            <label for="first_name">First Name:</label><br>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($profileData['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>

            <label for="last_name">Last Name:</label><br>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($profileData['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>

            <label for="email">Email:</label><br>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profileData['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>

            <label for="phone_number">Phone Number:</label><br>
            <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($profileData['phone_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><br><br>
        </section>

        <section>
            <h2>Shipping Address</h2>
            <label for="address_street">Street Address:</label><br>
            <input type="text" id="address_street" name="address_street" value="<?php echo htmlspecialchars($profileData['address_street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>

            <label for="address_city">City:</label><br>
            <input type="text" id="address_city" name="address_city" value="<?php echo htmlspecialchars($profileData['address_city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>

            <label for="address_state">State/Province:</label><br>
            <input type="text" id="address_state" name="address_state" value="<?php echo htmlspecialchars($profileData['address_state'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>

            <label for="address_zip">Zip/Postal Code:</label><br>
            <input type="text" id="address_zip" name="address_zip" value="<?php echo htmlspecialchars($profileData['address_zip'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>

            <label for="address_country">Country:</label><br>
            <input type="text" id="address_country" name="address_country" value="<?php echo htmlspecialchars($profileData['address_country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>
        </section>

        <section>
            <h2>Billing Information (Address)</h2>
            <label for="billing_street">Street Address:</label><br>
            <input type="text" id="billing_street" name="billing_street" value="<?php echo htmlspecialchars($profileData['billing_street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>

            <label for="billing_city">City:</label><br>
            <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($profileData['billing_city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>

            <label for="billing_state">State/Province:</label><br>
            <input type="text" id="billing_state" name="billing_state" value="<?php echo htmlspecialchars($profileData['billing_state'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>

            <label for="billing_zip">Zip/Postal Code:</label><br>
            <input type="text" id="billing_zip" name="billing_zip" value="<?php echo htmlspecialchars($profileData['billing_zip'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>

            <label for="billing_country">Country:</label><br>
            <input type="text" id="billing_country" name="billing_country" value="<?php echo htmlspecialchars($profileData['billing_country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required><br><br>
        </section>

        <button type="submit">Update Profile</button>
        <p id="responseMessage"></p>
    </form>

    <script>
        document.getElementById('profileForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const responseMessage = document.getElementById('responseMessage');

            fetch(form.action, {
                method: form.method,
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                return response.json().then(data => {
                    if (!response.ok) {
                        return Promise.reject(data);
                    }
                    return data;
                });
            })
            .then(data => {
                if (data.success) {
                    responseMessage.textContent = data.message;
                    responseMessage.style.color = 'green';
                } else {
                    responseMessage.textContent = data.message;
                    responseMessage.style.color = 'red';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                responseMessage.textContent = error.message || 'An unexpected error occurred. Please try again.';
                responseMessage.style.color = 'red';
            });
        });
    </script>
</body>
</html>
?>