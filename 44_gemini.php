<?php

// File: classes/Database.php

class Database
{
    private $host = 'localhost';
    private $db_name = 'db_ecommerce';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch (PDOException $exception) {
            // Log error in production. For this example, re-throwing.
            throw new Exception("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}


// File: classes/ProfileManager.php

class ProfileManager
{
    private $conn;
    private $table_name = "profiles";

    public function __construct(Database $db)
    {
        $this->conn = $db->getConnection();
    }

    public function updateProfile($userId, $data)
    {
        // For card_number, card_expiry, card_cvv:
        // In a real application, these should NEVER be stored directly.
        // They should be tokenized by a payment gateway, or if absolutely necessary to store,
        // robustly encrypted with a strong key management system. CVV should ideally never be stored.
        // For the purpose of this exercise, we store them as strings as requested by schema implication.

        $query = "UPDATE " . $this->table_name . "
                  SET
                      first_name = :first_name,
                      last_name = :last_name,
                      email = :email,
                      phone = :phone,
                      address_street = :address_street,
                      address_city = :address_city,
                      address_state = :address_state,
                      address_zip = :address_zip,
                      address_country = :address_country,
                      billing_street = :billing_street,
                      billing_city = :billing_city,
                      billing_state = :billing_state,
                      billing_zip = :billing_zip,
                      billing_country = :billing_country,
                      card_number = :card_number,
                      card_expiry = :card_expiry,
                      card_cvv = :card_cvv,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE
                      user_id = :user_id";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind values
        $stmt->bindParam(':first_name', htmlspecialchars(strip_tags($data['first_name'])));
        $stmt->bindParam(':last_name', htmlspecialchars(strip_tags($data['last_name'])));
        $stmt->bindParam(':email', htmlspecialchars(strip_tags($data['email'])));
        $stmt->bindParam(':phone', htmlspecialchars(strip_tags($data['phone'])));
        $stmt->bindParam(':address_street', htmlspecialchars(strip_tags($data['address_street'])));
        $stmt->bindParam(':address_city', htmlspecialchars(strip_tags($data['address_city'])));
        $stmt->bindParam(':address_state', htmlspecialchars(strip_tags($data['address_state'])));
        $stmt->bindParam(':address_zip', htmlspecialchars(strip_tags($data['address_zip'])));
        $stmt->bindParam(':address_country', htmlspecialchars(strip_tags($data['address_country'])));
        $stmt->bindParam(':billing_street', htmlspecialchars(strip_tags($data['billing_street'])));
        $stmt->bindParam(':billing_city', htmlspecialchars(strip_tags($data['billing_city'])));
        $stmt->bindParam(':billing_state', htmlspecialchars(strip_tags($data['billing_state'])));
        $stmt->bindParam(':billing_zip', htmlspecialchars(strip_tags($data['billing_zip'])));
        $stmt->bindParam(':billing_country', htmlspecialchars(strip_tags($data['billing_country'])));
        $stmt->bindParam(':card_number', htmlspecialchars(strip_tags($data['card_number'])));
        $stmt->bindParam(':card_expiry', htmlspecialchars(strip_tags($data['card_expiry'])));
        $stmt->bindParam(':card_cvv', htmlspecialchars(strip_tags($data['card_cvv'])));
        $stmt->bindParam(':user_id', $userId);

        try {
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            // Log error in production
            error_log("Profile update failed: " . $e->getMessage());
            return false;
        }
    }

    public function getProfile($userId)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }
}


// File: handlers/update_profile.php

require_once '../classes/Database.php';
require_once '../classes/ProfileManager.php';

header("Content-Type: application/json; charset=UTF-8");

$response = [
    "status" => "error",
    "message" => "An unknown error occurred."
];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response["message"] = "Invalid request method.";
    echo json_encode($response);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data)) {
    $data = $_POST; // Fallback for standard form posts
}

// Basic server-side validation
$errors = [];

// User ID (for a real app, this should come from session, not form)
$userId = isset($data['user_id']) ? filter_var($data['user_id'], FILTER_VALIDATE_INT) : null;
if (!$userId) {
    $errors['user_id'] = "User ID is required.";
}

// Name
if (empty($data['first_name'])) {
    $errors['first_name'] = "First name is required.";
}
if (empty($data['last_name'])) {
    $errors['last_name'] = "Last name is required.";
}

// Contact Details
if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = "Valid email is required.";
}
if (!empty($data['phone']) && !preg_match("/^\+?[0-9\s-()]{7,20}$/", $data['phone'])) {
    $errors['phone'] = "Invalid phone number format.";
}

// Address
if (empty($data['address_street'])) {
    $errors['address_street'] = "Street address is required.";
}
if (empty($data['address_city'])) {
    $errors['address_city'] = "City is required.";
}
if (empty($data['address_state'])) {
    $errors['address_state'] = "State/Province is required.";
}
if (empty($data['address_zip']) || !preg_match("/^[0-9A-Za-z- ]{3,10}$/", $data['address_zip'])) {
    $errors['address_zip'] = "Valid zip/postal code is required.";
}
if (empty($data['address_country'])) {
    $errors['address_country'] = "Country is required.";
}

// Billing Information
// Assuming billing info can be optional or required based on business logic.
// For this example, treating as required fields.
if (empty($data['billing_street'])) {
    $errors['billing_street'] = "Billing street address is required.";
}
if (empty($data['billing_city'])) {
    $errors['billing_city'] = "Billing city is required.";
}
if (empty($data['billing_state'])) {
    $errors['billing_state'] = "Billing state/province is required.";
}
if (empty($data['billing_zip']) || !preg_match("/^[0-9A-Za-z- ]{3,10}$/", $data['billing_zip'])) {
    $errors['billing_zip'] = "Valid billing zip/postal code is required.";
}
if (empty($data['billing_country'])) {
    $errors['billing_country'] = "Billing country is required.";
}

// Card details - simplified validation for demonstration. In production, use payment gateway client-side and server-side validation.
if (!empty($data['card_number']) && !preg_match("/^[0-9]{13,19}$/", str_replace(' ', '', $data['card_number']))) {
    $errors['card_number'] = "Invalid card number.";
}
if (!empty($data['card_expiry']) && !preg_match("/^(0[1-9]|1[0-2])\/?([0-9]{2})$/", $data['card_expiry'], $matches)) {
    $errors['card_expiry'] = "Invalid expiry date (MM/YY).";
} elseif (!empty($data['card_expiry'])) {
    $month = (int)$matches[1];
    $year = (int)$matches[2];
    $currentYear = (int)date('y');
    $currentMonth = (int)date('m');

    if ($year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
        $errors['card_expiry'] = "Expiry date must be in the future.";
    }
}
if (!empty($data['card_cvv']) && !preg_match("/^[0-9]{3,4}$/", $data['card_cvv'])) {
    $errors['card_cvv'] = "Invalid CVV.";
}

if (!empty($errors)) {
    $response["message"] = "Validation failed.";
    $response["errors"] = $errors;
    echo json_encode($response);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $profileManager = new ProfileManager($database);

    $success = $profileManager->updateProfile($userId, $data);

    if ($success) {
        $response["status"] = "success";
        $response["message"] = "Profile updated successfully.";
    } else {
        $response["message"] = "Failed to update profile due to a database error.";
    }
} catch (Exception $e) {
    $response["message"] = "An error occurred: " . $e->getMessage();
}

echo json_encode($response);


// File: public/user_profile.php

require_once '../classes/Database.php';
require_once '../classes/ProfileManager.php';

// In a real application, the user_id would come from the authenticated session.
// For this example, we'll use a hardcoded user_id for demonstration.
$currentUserId = 1;

$profileData = [];
try {
    $database = new Database();
    $profileManager = new ProfileManager($database);
    $profileData = $profileManager->getProfile($currentUserId);
} catch (Exception $e) {
    // Log the error. Display a generic message to the user.
    error_log("Error loading profile: " . $e->getMessage());
    $profileData = []; // Ensure it's an empty array on error
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
    <form id="profileForm" action="../handlers/update_profile.php" method="POST">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($currentUserId); ?>">

        <h2>Personal Information</h2>
        <div>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($profileData['first_name'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($profileData['last_name'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profileData['email'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="phone">Phone:</label>
            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($profileData['phone'] ?? ''); ?>">
        </div>

        <h2>Address Information</h2>
        <div>
            <label for="address_street">Street:</label>
            <input type="text" id="address_street" name="address_street" value="<?php echo htmlspecialchars($profileData['address_street'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="address_city">City:</label>
            <input type="text" id="address_city" name="address_city" value="<?php echo htmlspecialchars($profileData['address_city'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="address_state">State/Province:</label>
            <input type="text" id="address_state" name="address_state" value="<?php echo htmlspecialchars($profileData['address_state'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="address_zip">Zip/Postal Code:</label>
            <input type="text" id="address_zip" name="address_zip" value="<?php echo htmlspecialchars($profileData['address_zip'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="address_country">Country:</label>
            <input type="text" id="address_country" name="address_country" value="<?php echo htmlspecialchars($profileData['address_country'] ?? ''); ?>" required>
        </div>

        <h2>Billing Information</h2>
        <div>
            <label for="billing_street">Billing Street:</label>
            <input type="text" id="billing_street" name="billing_street" value="<?php echo htmlspecialchars($profileData['billing_street'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="billing_city">Billing City:</label>
            <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($profileData['billing_city'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="billing_state">Billing State/Province:</label>
            <input type="text" id="billing_state" name="billing_state" value="<?php echo htmlspecialchars($profileData['billing_state'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="billing_zip">Billing Zip/Postal Code:</label>
            <input type="text" id="billing_zip" name="billing_zip" value="<?php echo htmlspecialchars($profileData['billing_zip'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="billing_country">Billing Country:</label>
            <input type="text" id="billing_country" name="billing_country" value="<?php echo htmlspecialchars($profileData['billing_country'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="card_number">Card Number:</label>
            <input type="text" id="card_number" name="card_number" value="<?php echo htmlspecialchars($profileData['card_number'] ?? ''); ?>">
        </div>
        <div>
            <label for="card_expiry">Card Expiry (MM/YY):</label>
            <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY" value="<?php echo htmlspecialchars($profileData['card_expiry'] ?? ''); ?>">
        </div>
        <div>
            <label for="card_cvv">Card CVV:</label>
            <input type="text" id="card_cvv" name="card_cvv" value="<?php echo htmlspecialchars($profileData['card_cvv'] ?? ''); ?>">
        </div>

        <button type="submit">Update Profile</button>
    </form>

    <div id="responseMessage"></div>

    <script>
        document.getElementById('profileForm').addEventListener('submit', async function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            const responseMessageDiv = document.getElementById('responseMessage');
            responseMessageDiv.innerHTML = '';

            try {
                const response = await fetch(form.action, {
                    method: form.method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.status === 'success') {
                    responseMessageDiv.style.color = 'green';
                    responseMessageDiv.textContent = result.message;
                } else {
                    responseMessageDiv.style.color = 'red';
                    let errorMessage = result.message;
                    if (result.errors) {
                        errorMessage += '<br><ul>';
                        for (const field in result.errors) {
                            errorMessage += `<li>${result.errors[field]}</li>`;
                        }
                        errorMessage += '</ul>';
                    }
                    responseMessageDiv.innerHTML = errorMessage;
                }
            } catch (error) {
                responseMessageDiv.style.color = 'red';
                responseMessageDiv.textContent = 'An unexpected error occurred: ' + error.message;
            }
        });
    </script>
</body>
</html>
?>