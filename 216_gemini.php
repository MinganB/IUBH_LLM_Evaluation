<!DOCTYPE html>
<html>
<head>
    <title>Inventory Data Input</title>
</head>
<body>
    <h1>Submit Inventory Data to Processing Module</h1>
    <form action="process.php" method="POST">
        <label for="jsonData">Enter JSON Data for Inventory Item:</label><br>
        <textarea id="jsonData" name="jsonData" rows="15" cols="70">
{
  "user_id": 12345,
  "item_id": "SKU-ABC-001",
  "quantity": 10,
  "location": "Warehouse-Alpha",
  "notes": "Received from supplier X",
  "timestamp": "2023-10-27T14:30:00Z"
}
        </textarea><br><br>
        <input type="submit" value="Process Inventory Data">
    </form>
</body>
</html>