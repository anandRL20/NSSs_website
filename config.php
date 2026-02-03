<?php
// Database Configuration
define('DB_HOST', 'sql210.iceiy.com');
define('DB_USER', 'icei_40854326');
define('DB_PASS', 'AUg6aXYHLMjc');
define('DB_NAME', 'icei_40854326_infophp');

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>