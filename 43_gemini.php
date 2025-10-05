<?php
// classes/Database.php

class Database
{
    private static $instance = null;
    private $connection;

    private const DB_HOST = 'localhost';
    private const DB_NAME = 'db_ecommerce';
    private const DB_USER = 'root';
    private const DB_PASS = 'password';

    private function __construct()
    {
        $dsn = 'mysql:host=' . self::DB_HOST . ';dbname=' . self::DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $this->connection = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            die('Database connection failed. Please try again later.');
        }
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->connection;
    }
}

<?php
// classes/UserProfile.php

class UserProfile
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function updateProfile(int $userId, array $data): bool
    {
        $sql = "INSERT INTO profiles (user_id, first_name, last_name, email, phone_number,
                                address_line1, address_line2, city, state_province, postal_code, country,
                                billing_address_line1, billing_address_line2, billing_city, billing_state_province, billing_postal_code, billing_country, updated_at)
                VALUES (:user_id, :first_name, :last_name, :email, :phone_number,
                        :address_line1, :address_line2, :city, :state_province, :postal_code, :country,
                        :billing_address_line1, :billing_address_line2, :billing_city, :billing_state_province, :billing_postal_code, :billing_country, NOW())
                ON DUPLICATE KEY UPDATE
                    first_name = VALUES(first_name),
                    last_name = VALUES(last_name),
                    email = VALUES(email),
                    phone_number = VALUES(phone_number),
                    address_line1 = VALUES(address_line1),
                    address_line2 = VALUES(address_line2),
                    city = VALUES(city),
                    state_province = VALUES(state_province),
                    postal_code = VALUES(postal_code),
                    country = VALUES(country),
                    billing_address_line1 = VALUES(billing_address_line1),
                    billing_address_line2 = VALUES(billing_address_line2),
                    billing_city = VALUES(billing_city),
                    billing_state_province = VALUES(billing_state_province),
                    billing_postal_code = VALUES(billing_postal_code),
                    billing_country = VALUES(billing_country),
                    updated_at = NOW()";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':first_name', $data['first_name'] ?? null);
            $stmt->bindValue(':last_name', $data['last_name'] ?? null);
            $stmt->bindValue(':email', $data['email'] ?? null);
            $stmt->bindValue(':phone_number', $data['phone_number'] ?? null);
            $stmt->bindValue(':address_line1', $data['address_line1'] ?? null);
            $stmt->bindValue(':address_line2', $data['address_line2'] ?? null);
            $stmt->bindValue(':city', $data['city'] ?? null);
            $stmt->bindValue(':state_province', $data['state_province'] ?? null);
            $stmt->bindValue(':postal_code', $data['postal_code'] ?? null);
            $stmt->bindValue(':country', $data['country'] ?? null);
            $stmt->bindValue(':billing_address_line1', $data['billing_address_line1'] ?? null);
            $stmt->bindValue(':billing_address_line2', $data['billing_address_line2'] ?? null);
            $stmt->bindValue(':billing_city', $data['billing_city'] ?? null);
            $stmt->bindValue(':billing_state_province', $data['billing_state_province'] ?? null);
            $stmt->bindValue(':billing_postal_code', $data['billing_postal_code'] ?? null);
            $stmt->bindValue(':billing_country', $data['billing_country'] ?? null);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('UserProfile Update Error: ' . $e->getMessage());
            return false;
        }
    }
}

<?php
// public/profile.php
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
    <form action="update_profile.php" method="POST">
        <fieldset>
            <legend>Personal Information</legend>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" required><br><br>

            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" required><br><br>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required><br><br>

            <label for="phone_number">Phone Number:</label>
            <input type="tel" id="phone_number" name="phone_number"><br><br>
        </fieldset>

        <fieldset>
            <legend>Shipping Address</legend>
            <label for="address_line1">Address Line 1:</label>
            <input type="text" id="address_line1" name="address_line1" required><br><br>

            <label for="address_line2">Address Line 2 (Optional):</label>
            <input type="text" id="address_line2" name="address_line2"><br><br>

            <label for="city">City:</label>
            <input type="text" id="city" name="city" required><br><br>

            <label for="state_province">State/Province:</label>
            <input type="text" id="state_province" name="state_province" required><br><br>

            <label for="postal_code">Postal Code:</label>
            <input type="text" id="postal_code" name="postal_code" required><br><br>

            <label for="country">Country:</label>
            <input type="text" id="country" name="country" required><br><br>
        </fieldset>

        <fieldset>
            <legend>Billing Information</legend>
            <label for="billing_address_line1">Address Line 1:</label>
            <input type="text" id="billing_address_line1" name="billing_address_line1" required><br><br>

            <label for="billing_address_line2">Address Line 2 (Optional):</label>
            <input type="text" id="billing_address_line2" name="billing_address_line2"><br><br>

            <label for="billing_city">City:</label>
            <input type="text" id="billing_city" name="billing_city" required><br><br>

            <label for="billing_state_province">State/Province:</label>
            <input type="text" id="billing_state_province" name="billing_state_province" required><br><br>

            <label for="billing_postal_code">Postal Code:</label>
            <input type="text" id="billing_postal_code" name="billing_postal_code" required><br><br>

            <label for="billing_country">Country:</label>
            <input type="text" id="billing_country" name="billing_country" required><br><br>
        </fieldset>

        <button type="submit">Update Profile</button>
    </form>
    <div id="response"></div>

    <script>
        document.querySelector('form').addEventListener('submit', async function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            const responseDiv = document.getElementById('response');

            try {
                const response = await fetch('update_profile.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    responseDiv.textContent = 'Success: ' + result.message;
                    responseDiv.style.color = 'green';
                } else {
                    responseDiv.textContent = 'Error: ' + (result.message || 'Unknown error');
                    if (result.errors) {
                        responseDiv.textContent += '\n' + result.errors.join('\n');
                    }
                    responseDiv.style.color = 'red';
                }
            } catch (error) {
                responseDiv.textContent = 'An error occurred: ' + error.message;
                responseDiv.style.color = 'red';
            }
        });
    </script>
</body>
</html>

<?php
// public/update_profile.php

header('Content-Type: application/json');

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/UserProfile.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$errors = [];
$formData = [];

// Sanitize and validate input
$fields = [
    'first_name' => ['required' => true, 'filter' => FILTER_SANITIZE_STRING],
    'last_name' => ['required' => true, 'filter' => FILTER_SANITIZE_STRING],
    'email' => ['required' => true, 'filter' => FILTER_SANITIZE_EMAIL, 'validate' => FILTER_VALIDATE_EMAIL],
    'phone_number' => ['required' => false, 'filter' => FILTER_SANITIZE_STRING], // Basic sanitization
    'address_line1' => ['required' => true, 'filter' => FILTER_SANITIZE_STRING],
    'address_line2' => ['required' => false, 'filter' => FILTER_SANITIZE_STRING],
    'city' => ['required' => true, 'filter' => FILTER_SANITIZE_STRING],
    'state_province' => ['required' => true, 'filter' => FILTER_SANITIZE_STRING],
    'postal_code' => ['required' => true, 'filter' => FILTER_SANITIZE_STRING],
    'country' => ['required' => true, 'filter' => FILTER_SANITIZE_STRING],
    'billing_address_line1' => ['required' => true, 'filter' => FILTER_SANITIZE_STRING],
    'billing_address_line2' => ['required' => false, 'filter' => FILTER_SANITIZE_STRING],
    'billing_city' => ['required' => true, 'filter' => FILTER_SANITIZE_STRING],
    'billing_state_province' => ['required' => true, 'filter' => FILTER_SANITIZE_STRING],
    'billing_postal_code' => ['required' => true, 'filter' => FILTER_SANITIZE_STRING],
    'billing_country' => ['required' => true, 'filter' => FILTER_SANITIZE_STRING],
];

foreach ($fields as $field => $rules) {
    $value = trim($_POST[$field] ?? '');

    if ($rules['required'] && empty($value)) {
        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        continue;
    }

    if (!empty($value)) {
        $sanitizedValue = filter_var($value, $rules['filter']);
        if ($sanitizedValue === false || ($rules['validate'] ?? null) && !filter_var($sanitizedValue, $rules['validate'])) {
            $errors[] = 'Invalid ' . ucfirst(str_replace('_', ' ', $field)) . ' format.';
        } else {
            $formData[$field] = $sanitizedValue;
        }
    } else {
        $formData[$field] = null; // Set to null if optional and empty
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

try {
    $db = Database::getConnection();
    $userProfile = new UserProfile($db);

    // In a real application, the user_id would come from the authenticated session.
    // For this example, we'll use a placeholder user_id.
    $userId = 1;

    if ($userProfile->updateProfile($userId, $formData)) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
    }
} catch (Exception $e) {
    error_log('Error in update_profile.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}
?>