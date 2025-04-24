<?php
// includes/db_config.php

// --- Database Credentials ---
define('DB_HOST', 'localhost');     // Or your DB host (e.g., 127.0.0.1)
define('DB_NAME', 'DBNAME');   // Your database name
define('DB_USER', 'DBUSER');  // Your database username
define('DB_PASS', 'DBUSER'); // Your database password
define('DB_CHARSET', 'utf8mb4');

// --- PDO Connection Setup ---
// Data Source Name (DSN)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// PDO Options for error handling and fetching
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on SQL errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements (recommended)
];

// Global PDO object variable
$pdo = null; // Initialize

try {
    // Attempt to create the PDO connection object
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Connection failed! Log the error securely and stop execution.
    // DO NOT show detailed errors like $e->getMessage() on a public site.
    error_log("Database Connection Error: " . $e->getMessage()); // Log detailed error to server log
    // Display a generic error message to the user
    die("Database connection failed. Please check server logs or contact the administrator.");
}

// If the script reaches here, $pdo holds the database connection object.
?>
