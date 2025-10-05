<?php

// session_handler.php
// This script simulates a successful login and sets up a new user session.

// Include configuration and logger class
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Logger.php';

// Set the log file path for the Logger
Logger::setLogFilePath(LOG_FILE_PATH);

// Configure session cookie parameters BEFORE session_start()
// Ensures HttpOnly and Secure attributes are set
session_set_cookie_params([
    'lifetime' => SESSION_COOKIE_LIFETIME,
    'path' => SESSION_COOKIE_PATH,
    'domain' => SESSION_COOKIE_DOMAIN,
    'secure' => SESSION_COOKIE_SECURE, // Requires HTTPS
    'httponly' => SESSION_COOKIE_HTTPONLY // Prevents JavaScript access
]);

// Prevent session ID from appearing in URLs
ini_set('session.use_trans_sid', 0);

// Start the session
session_start();

// Generate a new session ID to prevent session fixation attacks
// Pass true to delete the old session file immediately
session_regenerate_id(true);

// Simulate a successful login by setting a user_id in the session
// In a real application, this would come from an authentication process
$_SESSION['user_id'] = 123; // Example user ID

// Set session creation timestamp for absolute timeout
$_SESSION['CREATED'] = time();

// Set last activity timestamp for idle timeout
$_SESSION['LAST_ACTIVITY'] = time();

// Log the session creation event
Logger::log('Session created for user', $_SESSION['user_id']);

// Return a JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Login successful. Session initiated.'
]);

exit;
?>