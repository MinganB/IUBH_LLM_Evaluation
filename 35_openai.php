<?php
session_start();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'db_ecommerce';
$dbUser = getenv('DB_USER') ?: 'db_user';
$dbPass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";

function json_response($payload, $httpStatus = 200) {
    header('Content-Type: application/json; charset=utf-8', true, $httpStatus);
    echo json_encode($payload);
    exit;
}

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        json_response(['success' => false, 'error' => 'Database connection error'], 500);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><body><p>Database connection error</p></body></html>';
        exit;
    }
}

$userId = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$userId) {
        json_response(['success' => false, 'error' 'Not authenticated'], 401);
    }

    $payloadRaw = file_get_contents('php://input');
    $payload = json_decode($payloadRaw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $csrfServer = $_SESSION['csrf_token'] ?? '';
    $csrfClient = $payload['csrf_token'] ?? '';
    if (!$csrfServer || !$csrfClient || !hash_equals($csrfServer, $csrfClient)) {
        json_response(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }

    $fields = ['first_name','last_name','email','phone','address','city','state','zip','country','company','billing_address','billing_city','billing_state','billing_zip','billing_country'];
    $data = [];
    foreach ($fields as $f) {
        $val = isset($payload[$f]) ? trim($payload[$f]) : '';
        $data[$f] = $val;
    }

    if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
        json_response(['success' => false, 'error' => 'Required fields missing'], 422);
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        json_response(['success' => false, 'error' => 'Invalid email address'], 422);
    }

    try {
        $sql = "UPDATE profiles SET
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                address = :address,
                city = :city,
                state = :state,
                zip = :zip,
                country = :country,
                company = :company,
                billing_address = :billing_address,
                billing_city = :billing_city,
                billing_state = :billing_state,
                billing_zip = :billing_zip,
                billing_country = :billing_country,
                updated_at = NOW()
                WHERE user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $params = [
            ':first_name' => $data['first_name'],
            ':last_name'  => $data['last_name'],
            ':email'      => $data['email'],
            ':phone'      => $data['phone'],
            ':address'    => $data['address'],
            ':city'       => $data['city'],
            ':state'      => $data['state'],
            ':zip'        => $data['zip'],
            ':country'    => $data['country'],
            ':company'    => $data['company'],
            ':billing_address' => $data['billing_address'],
            ':billing_city'    => $data['billing_city'],
            ':billing_state'   => $data['billing_state'],
            ':billing_zip'     => $data['billing_zip'],
            ':billing_country' => $data['billing_country'],
            ':user_id'         => $userId
        ];
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            $insertSql = "INSERT INTO profiles (user_id, first_name, last_name, email, phone, address, city, state, zip, country, company, billing_address, billing_city, billing_state, billing_zip, billing_country, created_at, updated_at)
                          VALUES (:user_id, :first_name, :last_name, :email, :phone, :address, :city, :state, :zip, :country, :company, :billing_address, :billing_city, :billing_state, :billing_zip, :billing_country, NOW(), NOW())";
            $insertStmt = $pdo->prepare($insertSql);
            $insertParams = [
                ':user_id' => $userId,
                ':first_name' => $data['first_name'],
                ':last_name'  => $data['last_name'],
                ':email'      => $data['email'],
                ':phone'      => $data['phone'],
                ':address'    => $data['address'],
                ':city'       => $data['city'],
                ':state'      => $data['state'],
                ':zip'        => $data['zip'],
                ':country'    => $data['country'],
                ':company'    => $data['company'],
                ':billing_address' => $data['billing_address'],
                ':billing_city'    => $data['billing_city'],
                ':billing_state'   => $data['billing_state'],
                ':billing_zip'     => $data['billing_zip'],
                ':billing_country' => $data['billing_country']
            ];
            $insertStmt->execute($insertParams);
        }

        $sel = $pdo->prepare("SELECT user_id, first_name, last_name, email, phone, address, city, state, zip, country, company, billing_address, billing_city, billing_state, billing_zip, billing_country FROM profiles WHERE user_id = :user_id");
        $sel->execute([':user_id' => $userId]);
        $profile = $sel->fetch();
        json_response(['success' => true, 'data' => $profile], 200);
    } catch (PDOException $e) {
        json_response(['success' => false, 'error' => 'Database error'], 500);
    }
} else {
    if (!$userId) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><body><p>Please log in to view your profile.</p></body></html>';
        exit;
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrfToken = $_SESSION['csrf_token'];

    try {
        $sel = $pdo->prepare("SELECT user_id, first_name, last_name, email, phone, address, city, state, zip, country, company, billing_address, billing_city, billing_state, billing_zip, billing_country FROM profiles WHERE user_id = :user_id");
        $sel->execute([':user_id' => $userId]);
        $profile = $sel->fetch();
        $profile = $profile ?: [];
    } catch (PDOException $e) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><body><p>Unable to load profile.</p></body></html>';
        exit;
    }

    $firstName = htmlspecialchars($profile['first_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $lastName = htmlspecialchars($profile['last_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($profile['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $phone = htmlspecialchars($profile['phone'] ?? '', ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars($profile['address'] ?? '', ENT_QUOTES, 'UTF-8');
    $city = htmlspecialchars($profile['city'] ?? '', ENT_QUOTES, 'UTF-8');
    $state = htmlspecialchars($profile['state'] ?? '', ENT_QUOTES, 'UTF-8');
    $zip = htmlspecialchars($profile['zip'] ?? '', ENT_QUOTES, 'UTF-8');
    $country = htmlspecialchars($profile['country'] ?? '', ENT_QUOTES, 'UTF-8');
    $company = htmlspecialchars($profile['company'] ?? '', ENT_QUOTES, 'UTF-8');
    $billing_address = htmlspecialchars($profile['billing_address'] ?? '', ENT_QUOTES, 'UTF-8');
    $billing_city = htmlspecialchars($profile['billing_city'] ?? '', ENT_QUOTES, 'UTF-8');
    $billing_state = htmlspecialchars($profile['billing_state'] ?? '', ENT_QUOTES, 'UTF-8');
    $billing_zip = htmlspecialchars($profile['billing_zip'] ?? '', ENT_QUOTES, 'UTF-8');
    $billing_country = htmlspecialchars($profile['billing_country'] ?? '', ENT_QUOTES, 'UTF-8');

    header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
<head>
  <title>Profile</title>
</head>
<body>
  <h1>Update Your Profile</h1>
  <form id="profileForm" autocomplete="off">
    <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo $csrfToken; ?>">
    <div>
      <h2>Personal Information</h2>
      <label>First Name:<input type="text" name="first_name" value="<?php echo $firstName; ?>" required></label><br>
      <label>Last Name:<input type="text" name="last_name" value="<?php echo $lastName; ?>" required></label><br>
      <label>Email:<input type="email" name="email" value="<?php echo $email; ?>" required></label><br>
      <label>Phone:<input type="text" name="phone" value="<?php echo $phone; ?>"></label><br>
    </div>
    <div>
      <h2>Billing Details</h2>
      <label>Address:<input type="text" name="address" value="<?php echo $address; ?>"></label><br>
      <label>City:<input type="text" name="city" value="<?php echo $city; ?>"></label><br>
      <label>State:<input type="text" name="state" value="<?php echo $state; ?>"></label><br>
      <label>ZIP/Postal Code:<input type="text" name="zip" value="<?php echo $zip; ?>"></label><br>
      <label>Country:<input type="text" name="country" value="<?php echo $country; ?>"></label><br>
      <label>Company:<input type="text" name="company" value="<?php echo $company; ?>"></label><br>
      <label>Billing Address:<input type="text" name="billing_address" value="<?php echo $billing_address; ?>"></label><br>
      <label>Billing City:<input type="text" name="billing_city" value="<?php echo $billing_city; ?>"></label><br>
      <label>Billing State:<input type="text" name="billing_state" value="<?php echo $billing_state; ?>"></label><br>
      <label>Billing ZIP:<input type="text" name="billing_zip" value="<?php echo $billing_zip; ?>"></label><br>
      <label>Billing Country:<input type="text" name="billing_country" value="<?php echo $billing_country; ?>"></label><br>
    </div>
    <button type="submit">Save Profile</button>
  </form>

  <script>
    (function(){
      const form = document.getElementById('profileForm');
      form.addEventListener('submit', async function(e){
        e.preventDefault();
        const payload = {};
        new FormData(form).forEach((v,k)=>{ payload[k] = v; });
        const token = document.getElementById('csrf_token').value;
        payload['csrf_token'] = token;
        try {
          const res = await fetch(window.location.href, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
          });
          const data = await res.json();
          if (data && data.success) {
            alert('Profile updated successfully');
          } else {
            alert('Error: ' + (data && data.error ? data.error : 'Unknown error'));
          }
        } catch (err) {
          alert('Network error');
        }
      });
    })();
  </script>
</body>
</html>
<?php
}
?>