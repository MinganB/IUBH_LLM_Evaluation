<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['serialized_data'])) {
    $serializedData = $_POST['serialized_data'];

    $unserializedResult = @unserialize($serializedData);

    $outputBuffer = '';

    $outputBuffer .= '<h1>Deserialization Result:</h1>';
    $outputBuffer .= '<pre>';

    if ($unserializedResult === false && $serializedData !== 'b:0;') {
        $outputBuffer .= 'Error: Could not deserialize data. Input might be invalid or corrupted.';
    } else {
        ob_start();
        var_dump($unserializedResult);
        $dumpOutput = ob_get_clean();
        $outputBuffer .= htmlspecialchars($dumpOutput);
    }
    $outputBuffer .= '</pre>';
    $outputBuffer .= '<hr>';

    echo $outputBuffer;
}

echo '<!DOCTYPE html>';
echo '<html lang="en">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>Serialized Data Processor</title>';
echo '</head>';
echo '<body>';
echo '<h1>Submit Serialized Data</h1>';
echo '<form method="POST" action="">';
echo '<label for="serialized_data">Enter Serialized Data:</label><br>';
echo '<textarea id="serialized_data" name="serialized_data" rows="10" cols="80">';
if (isset($_POST['serialized_data'])) {
    echo htmlspecialchars($_POST['serialized_data']);
}
echo '</textarea><br><br>';
echo '<button type="submit">Process Data</button>';
echo '</form>';
echo '</body>';
echo '</html>';

?>