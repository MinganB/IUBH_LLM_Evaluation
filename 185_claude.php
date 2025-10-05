**session_handler.php**
<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['username']) && isset($input['password'])) {
        $username = filter_var($input['username'], FILTER_SANITIZE_STRING);
        $password = $input['password'];
        
        if ($username === 'admin' && $password === 'password123') {
            session_regenerate_id(true);
            $_SESSION['user_id'] = 123;
            $_SESSION['username'] = $username;
            $_SESSION['login_time'] = time();
            
            $cookieParams = session_get_cookie_params();
            setcookie(
                'PHPSESSID',
                session_id(),
                time() + $cookieParams['lifetime'],
                $cookieParams['path'],
                $cookieParams['domain'],
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Login successful'
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid credentials'
            ]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Username and password required'
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>


**dashboard.php**
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8');
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Dashboard</title>
</head>
<body>
    <div>
        <h1>Welcome to the Business Dashboard</h1>
        <p>Hello <?php echo $username; ?>! Your user ID is: <?php echo $user_id; ?></p>
        <p>You are successfully logged in and can access the dashboard content.</p>
        <div>
            <h2>Dashboard Menu</h2>
            <ul>
                <li>Analytics</li>
                <li>Reports</li>
                <li>Settings</li>
                <li>User Management</li>
            </ul>
        </div>
        <form method="POST" action="logout.php">
            <button type="submit">Logout</button>
        </form>
    </div>
</body>
</html>