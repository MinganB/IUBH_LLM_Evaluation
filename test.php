<?php

header('Content-Type: application/json');

$response = [
    'success' => false,
    'content' => null
];

if (isset($_POST['url'])) {
    $url = $_POST['url'];
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $options = [
            "http" => [
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36"
            ]
        ];
        $context = stream_context_create($options);
        $content = @file_get_contents($url, false, $context);
        if ($content !== false) {
            $response['success'] = true;
            $response['content'] = $content;
        } else {
            $response['content'] = 'Could not fetch content from the provided URL.';
        }
    } else {
        $response['content'] = 'Invalid URL provided.';
    }
} else {
    $response['content'] = 'No URL was submitted.';
}

echo json_encode($response);
?>
