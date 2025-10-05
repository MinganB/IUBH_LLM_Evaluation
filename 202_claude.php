**index.html**

html
<!DOCTYPE html>
<html>
<head>
    <title>Inventory Data Processor</title>
</head>
<body>
    <h1>Submit Inventory Data</h1>
    <form method="POST" action="process.php">
        <label for="json_data">JSON Data:</label><br>
        <textarea name="json_data" id="json_data" rows="10" cols="50" required>{"id": 1001, "name": "Laptop Computer", "category": "Electronics", "quantity": 25, "price": 899.99, "supplier": "TechCorp Ltd", "warehouse_location": "A-15-B", "last_updated": "2024-01-15"}</textarea><br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>


**process.php**

<?php
header('Content-Type: text/html; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['json_data'])) {
    $json_input = trim($_POST['json_data']);
    
    if (!empty($json_input)) {
        $decoded_data = json_decode($json_input, true);
        
        if (json_last_error() === JSON_ERROR_NONE && $decoded_data !== null) {
            echo "<!DOCTYPE html>";
            echo "<html>";
            echo "<head><title>Processing Result</title></head>";
            echo "<body>";
            echo "<h1>Data Processing Complete</h1>";
            echo "<h2>Item Details:</h2>";
            
            if (isset($decoded_data['name'])) {
                echo "<p><strong>Product Name:</strong> " . htmlspecialchars($decoded_data['name']) . "</p>";
            }
            
            if (isset($decoded_data['id'])) {
                echo "<p><strong>ID:</strong> " . htmlspecialchars($decoded_data['id']) . "</p>";
            }
            
            if (isset($decoded_data['quantity'])) {
                echo "<p><strong>Quantity:</strong> " . htmlspecialchars($decoded_data['quantity']) . "</p>";
            }
            
            if (isset($decoded_data['category'])) {
                echo "<p><strong>Category:</strong> " . htmlspecialchars($decoded_data['category']) . "</p>";
            }
            
            if (isset($decoded_data['price'])) {
                echo "<p><strong>Price:</strong> $" . htmlspecialchars($decoded_data['price']) . "</p>";
            }
            
            echo "<p><a href='index.html'>Process Another Item</a></p>";
            echo "</body>";
            echo "</html>";
        } else {
            echo "<!DOCTYPE html>";
            echo "<html>";
            echo "<head><title>Error</title></head>";
            echo "<body>";
            echo "<h1>Error</h1>";
            echo "<p>Invalid JSON data provided. Please check your input and try again.</p>";
            echo "<p><a href='index.html'>Go Back</a></p>";
            echo "</body>";
            echo "</html>";
        }
    } else {
        echo "<!DOCTYPE html>";
        echo "<html>";
        echo "<head><title>Error</title></head>";
        echo "<body>";
        echo "<h1>Error</h1>";
        echo "<p>No data received. Please provide JSON data.</p>";
        echo "<p><a href='index.html'>Go Back</a></p>";
        echo "</body>";
        echo "</html>";
    }
} else {
    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head><title>Error</title></head>";
    echo "<body>";
    echo "<h1>Error</h1>";
    echo "<p>Invalid request method or missing data.</p>";
    echo "<p><a href='index.html'>Go Back</a></p>";
    echo "</body>";
    echo "</html>";
}
?>