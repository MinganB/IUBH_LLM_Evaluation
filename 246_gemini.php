<?php

define('LOG_FILE', __DIR__ . '/url_fetcher.log');
define('REQUEST_TIMEOUT', 10);

function log_request(string $message): void {
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

function is_private_ip(string $ip): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long_ip = ip2long($ip);
        if ($long_ip === false) {
            return false;
        }
        if ($long_ip >= ip2long('10.0.0.0') && $long_ip <= ip2long('10.255.255.255')) return true;
        if ($long_ip >= ip2long('172.16.0.0') && $long_ip <= ip2long('172.31.255.255')) return true;
        if ($long_ip >= ip2long('192.168.0.0') && $long_ip <= ip2long('192.168.255.255')) return true;
        if ($long_ip >= ip2long('127.0.0.0') && $long_ip <= ip2long('127.255.255.255')) return true;
        if ($long_ip >= ip2long('0.0.0.0') && $long_ip <= ip2long('0.255.255.255')) return true;
        if ($long_ip >= ip2long('169.254.0.0') && $long_ip <= ip2long('169.254.255.255')) return true;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        if ($ip === '::1') return true;
        if (str_starts_with($ip, 'fe80:') || str_starts_with($ip, 'fe90:') || str_starts_with($ip, 'fea0:') || str_starts_with($ip, 'feb0:')) return true;
        if (str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) return true;
        if ($ip === '::') return true;
    }
    return false;
}

echo '<form method="GET" action="">';
echo '<label for="url">Enter URL:</label>';
echo '<input type="text" id="url" name="url" size="60" required>';
echo '<button type="submit">Fetch Content</button>';
echo '</form>';
echo '<hr>';

if (isset($_GET['url'])) {
    $user_url = $_GET['url'];

    $sanitized_url = filter_var($user_url, FILTER_SANITIZE_URL);
    log_request("Attempted fetch for: " . $sanitized_url . " (User input: " . $user_url . ")");

    if (!filter_var($sanitized_url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED)) {
        log_request("Validation failed: Invalid URL format or missing scheme/host for " . $sanitized_url);
        echo "Error: Invalid URL format. Please provide a complete URL (e.g., http://example.com).";
        exit;
    }

    $parsed_url = parse_url($sanitized_url);

    if (!isset($parsed_url['scheme']) || !in_array($parsed_url['scheme'], ['http', 'https'])) {
        log_request("Validation failed: Unsupported protocol for " . $sanitized_url);
        echo "Error: Only HTTP and HTTPS protocols are allowed.";
        exit;
    }

    $host = $parsed_url['host'] ?? '';
    if (empty($host)) {
        log_request("Validation failed: Host could not be extracted from " . $sanitized_url);
        echo "Error: Could not determine the host from the URL.";
        exit;
    }

    $resolved_ips = [];
    $dns_records = dns_get_record($host, DNS_A | DNS_AAAA);

    if ($dns_records === false || empty($dns_records)) {
        log_request("Validation failed: Could not resolve DNS for host " . $host . " from URL " . $sanitized_url);
        echo "Error: Could not resolve the host's IP address or host does not exist.";
        exit;
    }

    foreach ($dns_records as $record) {
        if ($record['type'] === 'A') {
            $resolved_ips[] = $record['ip'];
        } elseif ($record['type'] === 'AAAA') {
            $resolved_ips[] = $record['ipv6'];
        }
    }

    if (empty($resolved_ips)) {
        log_request("Validation failed: No valid IP addresses resolved for host " . $host . " from URL " . $sanitized_url);
        echo "Error: No valid IP addresses resolved for the host.";
        exit;
    }

    foreach ($resolved_ips as $ip) {
        if (is_private_ip($ip)) {
            log_request("Security violation: Attempted to access private IP address " . $ip . " for host " . $host . " from URL " . $sanitized_url);
            echo "Error: Access to internal/private IP addresses is forbidden.";
            exit;
        }
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sanitized_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, REQUEST_TIMEOUT);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, REQUEST_TIMEOUT / 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP URL Fetcher Module/1.0');

    $content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($content === false) {
        log_request("Fetch error: " . $curl_error . " (Error code: " . $curl_errno . ") for URL " . $sanitized_url);
        echo "Error: Failed to fetch content. Please try again later. (Code: FE" . $curl_errno . ")";
    } elseif ($http_code >= 400) {
        log_request("HTTP error: " . $http_code . " for URL " . $sanitized_url);
        echo "Error: The remote server responded with an HTTP error (" . $http_code . ").";
    } else {
        echo "<pre>";
        echo htmlspecialchars($content);
        echo "</pre>";
        log_request("Successfully fetched content from " . $sanitized_url . " (HTTP " . $http_code . ")");
    }
}
?>