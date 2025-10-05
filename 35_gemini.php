<?php

// File: classes/Database.php

class Database {
    private static ?PDO $instance = null;
    private string $host = 'localhost';
    private string $db_name = 'db_ecommerce';
    private string $username = 'root';
    private string $password = '';

    private function __construct() {
        try {
            self::$instance = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("Database connection failed.");
        }
    }

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            new Database();
        }
        return self::$instance;
    }
}

<?php

// File: classes/ProfileManager.php

class ProfileManager {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getProfileByUserId(int $userId): ?array {
        $stmt = $this->db->prepare("SELECT id, user_id, first_name, last_name, email, address, city, state, zip_code, country FROM profiles WHERE user_id = :user_id LIMIT 1");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $profile = $stmt->fetch();
        return $profile ?: null;
    }

    public function updateProfile(int $userId, array $data): bool {
        $fields = [];
        $params = [':user_id' => $userId];

        $allowedFields = ['first_name', 'last_name', 'email', 'address', 'city', 'state', 'zip_code', 'country'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "`{$field}` = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fieldsString = implode(', ', $fields);
        $params[':updated_at'] = date('Y-m-d H:i:s');

        $sql = "UPDATE profiles SET {$fieldsString}, updated_at = :updated_at WHERE user_id = :user_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function createProfile(int $userId, array $data): bool {
        $sql = "INSERT INTO profiles (user_id, first_name, last_name, email, address, city, state, zip_code, country, created_at, updated_at) VALUES (:user_id, :first_name, :last_name, :email, :address, :city, :state, :zip_code, :country, :created_at, :updated_at)";
        
        $params = [
            ':user_id' => $userId,
            ':first_name' => $data['first_name'] ?? null,
            ':last_name' => $data['last_name'] ?? null,
            ':email' => $data['email'] ?? null,
            ':address' => $data['address'] ?? null,
            ':city' => $data['city'] ?? null,
            ':state' => $data['state'] ?? null,
            ':zip_code' => $data['zip_code'] ?? null,
            ':country' => $data['country'] ?? null,
            ':created_at' => date('Y-m-d H:i:s'),
            ':updated_at' => date('Y-m-d H:i:s')
        ];

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            return false;
        }
    }
}

<?php

// File: public/profile.php

session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ProfileManager.php';

$userId = $_SESSION['user_id'];
$profileData = [];
$error = '';

try {
    $db = Database::getConnection();
    $profileManager = new ProfileManager($db);
    $profileData = $profileManager->getProfileByUserId($userId);

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

} catch (Exception $e) {
    $error = "An error occurred.";
}

$firstName = htmlspecialchars($profileData['first_name'] ?? '');
$lastName = htmlspecialchars($profileData['last_name'] ?? '');
$email = htmlspecialchars($profileData['email'] ?? '');
$address = htmlspecialchars($profileData['address'] ?? '');
$city = htmlspecialchars($profileData['city'] ?? '');
$state = htmlspecialchars($profileData['state'] ?? '');
$zipCode = htmlspecialchars($profileData['zip_code'] ?? '');
$country = htmlspecialchars($profileData['country'] ?? '');
$csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '');
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

    <?php if ($error): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <div id="responseMessage" style="margin-bottom: 15px;"></div>

    <form id="profileForm" method="POST" action="/handlers/profile_update.php">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

        <h2>Personal Information</h2>
        <div>
            <label for="first_name">First Name:</label><br>
            <input type="text" id="first_name" name="first_name" value="<?php echo $firstName; ?>" required>
        </div>
        <div>
            <label for="last_name">Last Name:</label><br>
            <input type="text" id="last_name" name="last_name" value="<?php echo $lastName; ?>" required>
        </div>
        <div>
            <label for="email">Email:</label><br>
            <input type="email" id="email" name="email" value="<?php echo $email; ?>" required>
        </div>

        <h2>Billing Details</h2>
        <div>
            <label for="address">Address:</label><br>
            <input type="text" id="address" name="address" value="<?php echo $address; ?>">
        </div>
        <div>
            <label for="city">City:</label><br>
            <input type="text" id="city" name="city" value="<?php echo $city; ?>">
        </div>
        <div>
            <label for="state">State:</label><br>
            <input type="text" id="state" name="state" value="<?php echo $state; ?>">
        </div>
        <div>
            <label for="zip_code">Zip Code:</label><br>
            <input type="text" id="zip_code" name="zip_code" value="<?php echo $zipCode; ?>">
        </div>
        <div>
            <label for="country">Country:</label><br>
            <input type="text" id="country" name="country" value="<?php echo $country; ?>">
        </div>

        <button type="submit">Update Profile</button>
    </form>

    <script>
        document.getElementById('profileForm').addEventListener('submit', async function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const responseMessage = document.getElementById('responseMessage');

            try {
                const response = await fetch(form.action, {
                    method: form.method,
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    responseMessage.style.color = 'green';
                    responseMessage.textContent = result.message;
                } else {
                    responseMessage.style.color = 'red';
                    responseMessage.textContent = result.message || 'An unknown error occurred.';
                }
            } catch (error) {
                console.error('Error submitting form:', error);
                responseMessage.style.color = 'red';
                responseMessage.textContent = 'An error occurred while connecting to the server.';
            }
        });
    </script>
</body>
</html>

<?php

// File: handlers/profile_update.php

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}
$userId = $_SESSION['user_id'];

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed.']);
    exit();
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ProfileManager.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    $db = Database::getConnection();
    $profileManager = new ProfileManager($db);

    $errors = [];
    $data = [];

    $data['first_name'] = trim($_POST['first_name'] ?? '');
    $data['last_name'] = trim($_POST['last_name'] ?? '');
    $data['email'] = trim($_POST['email'] ?? '');

    if (empty($data['first_name'])) { $errors[] = 'First name is required.'; }
    if (empty($data['last_name'])) { $errors[] = 'Last name is required.'; }
    if (empty($data['email'])) { $errors[] = 'Email is required.'; }
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Invalid email format.'; }

    $data['address'] = trim($_POST['address'] ?? '');
    $data['city'] = trim($_POST['city'] ?? '');
    $data['state'] = trim($_POST['state'] ?? '');
    $data['zip_code'] = trim($_POST['zip_code'] ?? '');
    $data['country'] = trim($_POST['country'] ?? '');

    if (!empty($errors)) {
        $response = ['success' => false, 'message' => implode(' ', $errors)];
        echo json_encode($response);
        exit();
    }
    
    $existingProfile = $profileManager->getProfileByUserId($userId);
    
    if ($existingProfile) {
        $updateSuccess = $profileManager->updateProfile($userId, $data);
        if ($updateSuccess) {
            $response = ['success' => true, 'message' => 'Profile updated successfully!'];
        } else {
            $response = ['success' => false, 'message' => 'Failed to update profile.'];
        }
    } else {
        $createSuccess = $profileManager->createProfile($userId, $data);
        if ($createSuccess) {
            $response = ['success' => true, 'message' => 'Profile created successfully!'];
        } else {
            $response = ['success' => false, 'message' => 'Failed to create profile.'];
        }
    }

} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Server error.'];
}

echo json_encode($response);
exit();
?>