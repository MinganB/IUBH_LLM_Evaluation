<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serializedData = $_POST['serialized_data'] ?? '';
    $result = '';
    $error = '';
    
    if (!empty($serializedData)) {
        try {
            $data = unserialize($serializedData);
            if ($data === false && $serializedData !== serialize(false)) {
                $error = 'Invalid serialized data format';
            } else {
                $result = $data;
            }
        } catch (Exception $e) {
            $error = 'Error deserializing data: ' . $e->getMessage();
        }
    } else {
        $error = 'No data provided';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Processor</title>
</head>
<body>
    <h1>Inventory Management - Data Processor</h1>
    
    <form method="POST" action="">
        <label for="serialized_data">Serialized Inventory Data:</label><br>
        <textarea name="serialized_data" id="serialized_data" rows="10" cols="80" placeholder="Paste serialized data here..."><?php echo isset($_POST['serialized_data']) ? htmlspecialchars($_POST['serialized_data']) : ''; ?></textarea><br><br>
        <button type="submit">Process Data</button>
    </form>
    
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <hr>
        <h2>Processing Results</h2>
        
        <?php if (!empty($error)): ?>
            <div style="color: red; border: 1px solid red; padding: 10px; margin: 10px 0;">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php else: ?>
            <div style="border: 1px solid green; padding: 10px; margin: 10px 0;">
                <h3>Deserialized Data:</h3>
                <?php if (is_array($result) || is_object($result)): ?>
                    <?php displayData($result); ?>
                <?php else: ?>
                    <p><strong>Value:</strong> <?php echo htmlspecialchars(var_export($result, true)); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if (is_array($result)): ?>
                <div style="border: 1px solid blue; padding: 10px; margin: 10px 0;">
                    <h3>Inventory Summary:</h3>
                    <?php generateInventorySummary($result); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>

<?php
function displayData($data, $level = 0) {
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
    
    if (is_array($data)) {
        echo '<ul>';
        foreach ($data as $key => $value) {
            echo '<li>';
            echo $indent . '<strong>' . htmlspecialchars($key) . ':</strong> ';
            
            if (is_array($value) || is_object($value)) {
                echo '<br>';
                displayData($value, $level + 1);
            } else {
                echo htmlspecialchars(var_export($value, true));
            }
            echo '</li>';
        }
        echo '</ul>';
    } elseif (is_object($data)) {
        echo '<ul>';
        echo '<li><strong>Object Type:</strong> ' . htmlspecialchars(get_class($data)) . '</li>';
        foreach (get_object_vars($data) as $property => $value) {
            echo '<li>';
            echo $indent . '<strong>' . htmlspecialchars($property) . ':</strong> ';
            
            if (is_array($value) || is_object($value)) {
                echo '<br>';
                displayData($value, $level + 1);
            } else {
                echo htmlspecialchars(var_export($value, true));
            }
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo htmlspecialchars(var_export($data, true));
    }
}

function generateInventorySummary($data) {
    $totalItems = 0;
    $categories = [];
    $lowStock = [];
    
    if (is_array($data)) {
        foreach ($data as $key => $item) {
            if (is_array($item)) {
                $quantity = 0;
                $category = 'Unknown';
                $name = $key;
                
                if (isset($item['quantity'])) {
                    $quantity = is_numeric($item['quantity']) ? (int)$item['quantity'] : 0;
                }
                
                if (isset($item['category'])) {
                    $category = $item['category'];
                }
                
                if (isset($item['name'])) {
                    $name = $item['name'];
                }
                
                $totalItems += $quantity;
                
                if (!isset($categories[$category])) {
                    $categories[$category] = 0;
                }
                $categories[$category] += $quantity;
                
                if ($quantity < 10) {
                    $lowStock[] = [
                        'name' => $name,
                        'quantity' => $quantity
                    ];
                }
            }
        }
    }
    
    echo '<p><strong>Total Items:</strong> ' . $totalItems . '</p>';
    
    if (!empty($categories)) {
        echo '<p><strong>Items by Category:</strong></p>';
        echo '<ul>';
        foreach ($categories as $category => $count) {
            echo '<li>' . htmlspecialchars($category) . ': ' . $count . ' items</li>';
        }
        echo '</ul>';
    }
    
    if (!empty($lowStock)) {
        echo '<p><strong>Low Stock Items (< 10):</strong></p>';
        echo '<ul>';
        foreach ($lowStock as $item) {
            echo '<li>' . htmlspecialchars($item['name']) . ': ' . $item['quantity'] . ' remaining</li>';
        }
        echo '</ul>';
    }
}
?>