**index.html**
html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Processor</title>
</head>
<body>
    <form action="process.php" method="POST">
        <label for="inventory_data">JSON Inventory Data:</label><br>
        <textarea id="inventory_data" name="inventory_data" rows="10" cols="50" required></textarea><br><br>
        <input type="submit" value="Process Inventory Data">
    </form>
</body>
</html>


**process.php**
<?php
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

if (!isset($_POST['inventory_data']) || empty(trim($_POST['inventory_data']))) {
    http_response_code(400);
    die('Invalid input data');
}

$json_data = trim($_POST['inventory_data']);

if (strlen($json_data) > 10240) {
    http_response_code(413);
    die('Data too large');
}

$decoded_data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die('Invalid JSON format: ' . json_last_error_msg());
}

if (!is_array($decoded_data)) {
    http_response_code(400);
    die('JSON must be an object or array');
}

function sanitize_output($data) {
    if (is_string($data)) {
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } elseif (is_numeric($data)) {
        return $data;
    } elseif (is_bool($data)) {
        return $data ? 'true' : 'false';
    } elseif (is_null($data)) {
        return 'null';
    } else {
        return htmlspecialchars(json_encode($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Processing Results</title>
</head>
<body>
    <h1>Inventory Data Processing Results</h1>
    
    <?php if (isset($decoded_data['product_name'])): ?>
        <h2>Product Name: <?php echo sanitize_output($decoded_data['product_name']); ?></h2>
    <?php endif; ?>
    
    <?php if (isset($decoded_data['quantity'])): ?>
        <p>Quantity: <?php echo sanitize_output($decoded_data['quantity']); ?></p>
    <?php endif; ?>
    
    <?php if (isset($decoded_data['price'])): ?>
        <p>Price: <?php echo sanitize_output($decoded_data['price']); ?></p>
    <?php endif; ?>
    
    <?php if (isset($decoded_data['category'])): ?>
        <p>Category: <?php echo sanitize_output($decoded_data['category']); ?></p>
    <?php endif; ?>
    
    <?php if (isset($decoded_data['sku'])): ?>
        <p>SKU: <?php echo sanitize_output($decoded_data['sku']); ?></p>
    <?php endif; ?>
    
    <h3>All Processed Data:</h3>
    <pre><?php echo sanitize_output(json_encode($decoded_data, JSON_PRETTY_PRINT)); ?></pre>
    
    <a href="index.html">Process Another Item</a>
</body>
</html>