<?php
session_start();

$host = '127.0.0.1';
$dbname = 'db_ecommerce';
$dbuser = 'db_user';
$dbpass = 'db_pass';
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['db' => $e->getMessage()]]);
    exit;
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$user_id) {
        echo json_encode(['success' => false, 'errors' => ['auth' => 'User not authenticated']]);
        exit;
    }

    $fields = ['first_name','last_name','email','phone','address_line1','address_line2','city','state','postal_code','country','billing_name','billing_address','billing_city','billing_state','billing_postal_code','billing_country'];
    $data = [];
    foreach ($fields as $f) {
        $val = isset($_POST[$f]) ? $_POST[$f] : '';
        $data[$f] = trim($val);
    }

    $errors = [];
    $required = ['first_name','last_name','email','address_line1','city','state','postal_code','country','billing_name','billing_address','billing_city','billing_state','billing_postal_code','billing_country'];
    foreach ($required as $r) {
        if (empty($data[$r])) {
            $errors[$r] = 'This field is required';
        }
    }
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT 1 FROM profiles WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $user_id]);
        $exists = (bool)$stmt->fetchColumn();

        $now = date('Y-m-d H:i:s');

        if ($exists) {
            $sql = 'UPDATE profiles SET first_name=:first_name,last_name=:last_name,email=:email,phone=:phone,address_line1=:address_line1,address_line2=:address_line2,city=:city,state=:state,postal_code=:postal_code,country=:country,billing_name=:billing_name,billing_address=:billing_address,billing_city=:billing_city,billing_state=:billing_state,billing_postal_code=:billing_postal_code,billing_country=:billing_country,updated_at=:updated_at WHERE user_id=:user_id';
            $params = [
                ':first_name' => $data['first_name'],
                ':last_name' => $data['last_name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'],
                ':address_line1' => $data['address_line1'],
                ':address_line2' => $data['address_line2'],
                ':city' => $data['city'],
                ':state' => $data['state'],
                ':postal_code' => $data['postal_code'],
                ':country' => $data['country'],
                ':billing_name' => $data['billing_name'],
                ':billing_address' => $data['billing_address'],
                ':billing_city' => $data['billing_city'],
                ':billing_state' => $data['billing_state'],
                ':billing_postal_code' => $data['billing_postal_code'],
                ':billing_country' => $data['billing_country'],
                ':updated_at' => $now,
                ':user_id' => $user_id
            ];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = 'INSERT INTO profiles (user_id, first_name, last_name, email, phone, address_line1, address_line2, city, state, postal_code, country, billing_name, billing_address, billing_city, billing_state, billing_postal_code, billing_country, created_at, updated_at) VALUES (:user_id, :first_name, :last_name, :email, :phone, :address_line1, :address_line2, :city, :state, :postal_code, :country, :billing_name, :billing_address, :billing_city, :billing_state, :billing_postal_code, :billing_country, :created_at, :updated_at)';
            $now = date('Y-m-d H:i:s');
            $params = [
                ':user_id' => $user_id,
                ':first_name' => $data['first_name'],
                ':last_name' => $data['last_name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'],
                ':address_line1' => $data['address_line1'],
                ':address_line2' => $data['address_line2'],
                ':city' => $data['city'],
                ':state' => $data['state'],
                ':postal_code' => $data['postal_code'],
                ':country' => $data['country'],
                ':billing_name' => $data['billing_name'],
                ':billing_address' => $data['billing_address'],
                ':billing_city' => $data['billing_city'],
                ':billing_state' => $data['billing_state'],
                ':billing_postal_code' => $data['billing_postal_code'],
                ':billing_country' => $data['billing_country'],
                ':created_at' => $now,
                ':updated_at' => $now
            ];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        $profile = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'postal_code' => $data['postal_code'],
            'country' => $data['country'],
            'billing_name' => $data['billing_name'],
            'billing_address' => $data['billing_address'],
            'billing_city' => $data['billing_city'],
            'billing_state' => $data['billing_state'],
            'billing_postal_code' => $data['billing_postal_code'],
            'billing_country' => $data['billing_country']
        ];

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully', 'profile' => $profile]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'errors' => ['db' => $e->getMessage()]]);
        exit;
    }
}

$loggedIn = $user_id > 0;
$current = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address_line1' => '',
    'address_line2' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
    'country' => '',
    'billing_name' => '',
    'billing_address' => '',
    'billing_city' => '',
    'billing_state' => '',
    'billing_postal_code' => '',
    'billing_country' => ''
];
if ($loggedIn) {
    try {
        $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone, address_line1, address_line2, city, state, postal_code, country, billing_name, billing_address, billing_city, billing_state, billing_postal_code, billing_country FROM profiles WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $user_id]);
        $row = $stmt->fetch();
        if ($row) {
            foreach ($row as $k => $v) {
                $current[$k] = $v;
            }
        }
    } catch (PDOException $e) {
        // ignore
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>User Profile</title></head>
<body>
<?php if (!$loggedIn): ?>
<p>Please log in to view and update your profile.</p>
<?php else: ?>
<form id="profileForm" method="POST" action="update_profile.php">
  <label>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($current['first_name']); ?>" required></label><br/>
  <label>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($current['last_name']); ?>" required></label><br/>
  <label>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($current['email']); ?>" required></label><br/>
  <label>Phone: <input type="text" name="phone" value="<?php echo htmlspecialchars($current['phone']); ?>"></label><br/>
  <label>Address Line 1: <input type="text" name="address_line1" value="<?php echo htmlspecialchars($current['address_line1']); ?>" required></label><br/>
  <label>Address Line 2: <input type="text" name="address_line2" value="<?php echo htmlspecialchars($current['address_line2']); ?>"></label><br/>
  <label>City: <input type="text" name="city" value="<?php echo htmlspecialchars($current['city']); ?>" required></label><br/>
  <label>State: <input type="text" name="state" value="<?php echo htmlspecialchars($current['state']); ?>" required></label><br/>
  <label>Postal Code: <input type="text" name="postal_code" value="<?php echo htmlspecialchars($current['postal_code']); ?>" required></label><br/>
  <label>Country: <input type="text" name="country" value="<?php echo htmlspecialchars($current['country']); ?>" required></label><br/>
  <hr/>
  <label>Billing Name: <input type="text" name="billing_name" value="<?php echo htmlspecialchars($current['billing_name']); ?>" required></label><br/>
  <label>Billing Address: <input type="text" name="billing_address" value="<?php echo htmlspecialchars($current['billing_address']); ?>" required></label><br/>
  <label>Billing City: <input type="text" name="billing_city" value="<?php echo htmlspecialchars($current['billing_city']); ?>" required></label><br/>
  <label>Billing State: <input type="text" name="billing_state" value="<?php echo htmlspecialchars($current['billing_state']); ?>" required></label><br/>
  <label>Billing Postal Code: <input type="text" name="billing_postal_code" value="<?php echo htmlspecialchars($current['billing_postal_code']); ?>" required></label><br/>
  <label>Billing Country: <input type="text" name="billing_country" value="<?php echo htmlspecialchars($current['billing_country']); ?>" required></label><br/>
  <button type="submit">Update Profile</button>
</form>
<div id="response"></div>
<script>
document.getElementById('profileForm').addEventListener('submit', function(e) {
  e.preventDefault();
  var form = this;
  var formData = new FormData(form);
  fetch('update_profile.php', {method: 'POST', body: formData})
    .then(res => res.json())
    .then(data => {
      var resp = document.getElementById('response');
      if (data.success) {
        resp.style.color = 'green';
        resp.textContent = data.message;
      } else {
        resp.style.color = 'red';
        if (data.errors) {
          var msg = Object.values(data.errors).join('; ');
          resp.textContent = 'Error: ' + msg;
        } else {
          resp.textContent = 'Error updating profile';
        }
      }
    })
    .catch(() => {
      var resp = document.getElementById('response');
      resp.style.color = 'red';
      resp.textContent = 'Request failed';
    });
});
</script>
<?php endif; ?>
</body>
</html>
?>