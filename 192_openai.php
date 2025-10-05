<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$LOG_PATH = __DIR__ . '/logs/processing.log';
ensureLogDirectory($LOG_PATH);

function ensureLogDirectory($path) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

// Safe deserialization: only allow scalar and array structures, no objects
function _parse_value($data, &$pos, $length) {
    if ($pos >= $length) return false;
    $type = $data[$pos];
    $pos++;

    switch ($type) {
        case 'N':
            if ($pos >= $length || $data[$pos] !== ';') return false;
            $pos++;
            return null;

        case 'b':
            if ($pos >= $length || $data[$pos] !== ':') return false;
            $pos++;
            if ($pos >= $length || ($data[$pos] !== '0' && $data[$pos] !== '1')) return false;
            $val = $data[$pos] === '1';
            $pos++;
            if ($pos >= $length || $data[$pos] !== ';') return false;
            $pos++;
            return $val;

        case 'i':
            if ($pos >= $length || $data[$pos] !== ':') return false;
            $pos++;
            $start = $pos;
            while ($pos < $length && $data[$pos] !== ';') $pos++;
            if ($pos >= $length) return false;
            $num = substr($data, $start, $pos - $start);
            $pos++;
            if ($num === '' || !preg_match('/^-?\d+$/', $num)) return false;
            return intval($num);

        case 'd':
            if ($pos >= $length || $data[$pos] !== ':') return false;
            $pos++;
            $start = $pos;
            while ($pos < $length && $data[$pos] !== ';') $pos++;
            if ($pos >= $length) return false;
            $numraw = substr($data, $start, $pos - $start);
            $pos++;
            if (!is_numeric($numraw)) return false;
            return floatval($numraw);

        case 's':
            if ($pos >= $length || $data[$pos] !== ':') return false;
            $pos++;
            $start = $pos;
            while ($pos < $length && ctype_digit($data[$pos])) $pos++;
            if ($pos == $start) return false;
            $strLen = intval(substr($data, $start, $pos - $start));
            if ($pos >= $length || $data[$pos] !== ':') return false;
            $pos++;
            if ($pos >= $length || $data[$pos] !== '"') return false;
            $pos++;
            $value = substr($data, $pos, $strLen);
            if (strlen($value) !== $strLen) return false;
            $pos += $strLen;
            if ($pos >= $length || $data[$pos] !== '"') return false;
            $pos++;
            if ($pos >= $length || $data[$pos] !== ';') return false;
            $pos++;
            return $value;

        case 'a':
            if ($pos >= $length || $data[$pos] !== ':') return false;
            $pos++;
            $start = $pos;
            while ($pos < $length && ctype_digit($data[$pos])) $pos++;
            if ($pos == $start) return false;
            $count = intval(substr($data, $start, $pos - $start));
            if ($pos >= $length || $data[$pos] !== ':') return false;
            $pos++;
            if ($pos >= $length || $data[$pos] !== '{') return false;
            $pos++;
            $arr = [];
            for ($i = 0; $i < $count; $i++) {
                $key = _parse_value($data, $pos, $length);
                if ($key === false) return false;
                $val = _parse_value($data, $pos, $length);
                if ($val === false) return false;
                $arr[$key] = $val;
            }
            if ($pos >= $length || $data[$pos] !== '}') return false;
            $pos++;
            return $arr;

        case 'O':
        case 'C':
        case 'R':
        case 'r':
            return false;

        default:
            return false;
    }
}

function safe_unserialize($data) {
    if (!is_string($data)) return false;
    $length = strlen($data);
    $pos = 0;
    $value = _parse_value($data, $pos, $length);
    if ($value === false) return false;
    if ($pos !== $length) return false;
    return $value;
}

function log_processing_attempt($source, $payload, $success, $note) {
    $path = __DIR__ . '/logs/processing.log';
    ensureLogDirectory($path);
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAIL';
    $hash = hash('sha256', $payload ?? '');
    $line = "$timestamp | source=$source | status=$status | hash=$hash | note=$note";
    @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$deserialized = null;
$processingAttempted = false;
$processingSuccess = false;
$rawSource = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = isset($_POST['payload']) ? $_POST['payload'] : '';
    $processingAttempted = true;
    if (trim($payload) === '') {
        log_processing_attempt($rawSource, $payload, false, 'Empty input');
        $processingSuccess = false;
        $deserialized = null;
    } else {
        $result = safe_unserialize($payload);
        if ($result === false) {
            log_processing_attempt($rawSource, $payload, false, 'Deserialization failed or disallowed data');
            $processingSuccess = false;
            $deserialized = null;
        } else {
            log_processing_attempt($rawSource, $payload, true, 'Deserialization successful');
            $deserialized = $result;
            $processingSuccess = true;
        }
    }
}

?>
<!doctype html>
<html>
<head>
    <title>Serialized Data Processor</title>
</head>
<body>
    <h1>Serialized Data Processor</h1>
    <form method="post" action="">
        <label for="payload">Serialized Data</label><br>
        <textarea id="payload" name="payload" rows="12" cols="100" placeholder="Enter PHP serialized data here"></textarea><br>
        <button type="submit">Deserialize</button>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($processingAttempted && $processingSuccess && $deserialized !== null) {
            echo '<h2>Deserialized Data</h2>';
            echo '<pre>' . htmlspecialchars(print_r($deserialized, true)) . '</pre>';
        } else {
            echo '<p>Deserialization failed or input was invalid.</p>';
        }
    } ?>
</body>
</html>
?>