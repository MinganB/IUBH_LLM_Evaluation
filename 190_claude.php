<?php
class SerializedDataProcessor {
    private $data;
    private $errors = [];

    public function serialize($data) {
        return serialize($data);
    }

    public function deserialize($serializedData) {
        try {
            $this->data = unserialize($serializedData);
            return $this->data;
        } catch (Exception $e) {
            $this->errors[] = "Deserialization failed: " . $e->getMessage();
            return false;
        }
    }

    public function getErrors() {
        return $this->errors;
    }

    public function displayData($data, $depth = 0) {
        $output = '';
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
        
        if (is_array($data)) {
            $output .= $indent . "Array (<br>";
            foreach ($data as $key => $value) {
                $output .= $indent . "&nbsp;&nbsp;&nbsp;&nbsp;[" . htmlspecialchars($key) . "] => ";
                if (is_array($value) || is_object($value)) {
                    $output .= "<br>" . $this->displayData($value, $depth + 1);
                } else {
                    $output .= htmlspecialchars($value) . "<br>";
                }
            }
            $output .= $indent . ")<br>";
        } elseif (is_object($data)) {
            $output .= $indent . get_class($data) . " Object (<br>";
            foreach (get_object_vars($data) as $key => $value) {
                $output .= $indent . "&nbsp;&nbsp;&nbsp;&nbsp;[" . htmlspecialchars($key) . "] => ";
                if (is_array($value) || is_object($value)) {
                    $output .= "<br>" . $this->displayData($value, $depth + 1);
                } else {
                    $output .= htmlspecialchars($value) . "<br>";
                }
            }
            $output .= $indent . ")<br>";
        } else {
            $output .= $indent . htmlspecialchars($data) . "<br>";
        }
        
        return $output;
    }
}

$processor = new SerializedDataProcessor();
$result = '';
$serializedInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'serialize') {
        $dataToSerialize = [];
        
        if (!empty($_POST['array_keys']) && !empty($_POST['array_values'])) {
            $keys = array_filter($_POST['array_keys']);
            $values = array_filter($_POST['array_values']);
            
            for ($i = 0; $i < min(count($keys), count($values)); $i++) {
                $dataToSerialize[$keys[$i]] = $values[$i];
            }
        }
        
        if (!empty($_POST['string_data'])) {
            $dataToSerialize['string_data'] = $_POST['string_data'];
        }
        
        if (!empty($_POST['number_data'])) {
            $dataToSerialize['number_data'] = $_POST['number_data'];
        }
        
        $serializedData = $processor->serialize($dataToSerialize);
        $result = "Serialized Data:<br><textarea readonly rows='5' cols='80'>" . htmlspecialchars($serializedData) . "</textarea>";
        
    } elseif (isset($_POST['action']) && $_POST['action'] === 'deserialize') {
        $serializedInput = $_POST['serialized_data'] ?? '';
        
        if (!empty($serializedInput)) {
            $deserializedData = $processor->deserialize($serializedInput);
            
            if ($deserializedData !== false) {
                $result = "Deserialized Data:<br><div style='border: 1px solid #ccc; padding: 10px; background-color: #f9f9f9; font-family: monospace;'>";
                $result .= $processor->displayData($deserializedData);
                $result .= "</div>";
            } else {
                $errors = $processor->getErrors();
                $result = "Error: " . implode('<br>', $errors);
            }
        } else {
            $result = "Error: Please provide serialized data to deserialize.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serialized Data Processor</title>
</head>
<body>
    <h1>Serialized Data Processor</h1>
    
    <h2>Create and Serialize Data</h2>
    <form method="POST">
        <input type="hidden" name="action" value="serialize">
        
        <h3>Array Data</h3>
        <div id="array-inputs">
            <div>
                <input type="text" name="array_keys[]" placeholder="Key" size="20">
                <input type="text" name="array_values[]" placeholder="Value" size="30">
            </div>
            <div>
                <input type="text" name="array_keys[]" placeholder="Key" size="20">
                <input type="text" name="array_values[]" placeholder="Value" size="30">
            </div>
            <div>
                <input type="text" name="array_keys[]" placeholder="Key" size="20">
                <input type="text" name="array_values[]" placeholder="Value" size="30">
            </div>
        </div>
        
        <h3>Additional Data</h3>
        <div>
            <label>String Data:</label><br>
            <input type="text" name="string_data" size="50" placeholder="Enter string data">
        </div>
        <br>
        <div>
            <label>Number Data:</label><br>
            <input type="number" name="number_data" placeholder="Enter number">
        </div>
        <br>
        <button type="submit">Serialize Data</button>
    </form>
    
    <hr>
    
    <h2>Deserialize Data</h2>
    <form method="POST">
        <input type="hidden" name="action" value="deserialize">
        
        <label>Serialized Data:</label><br>
        <textarea name="serialized_data" rows="5" cols="80" placeholder="Paste serialized data here"><?php echo htmlspecialchars($serializedInput); ?></textarea><br><br>
        
        <button type="submit">Deserialize Data</button>
    </form>
    
    <?php if (!empty($result)): ?>
        <hr>
        <h2>Result</h2>
        <div><?php echo $result; ?></div>
    <?php endif; ?>
    
    <hr>
    <h2>Sample Serialized Data</h2>
    <p>You can test with this sample:</p>
    <textarea readonly rows="3" cols="80">a:3:{s:4:"name";s:4:"John";s:3:"age";i:30;s:5:"email";s:15:"john@email.com";}</textarea>
    
</body>
</html>
?>