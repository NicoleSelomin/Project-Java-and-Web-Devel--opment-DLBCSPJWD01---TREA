<?php
/**
 * Database Connection Script (db_connect.php)
 *
 * Creates a PDO connection ($pdo) for use throughout the application.
 * Uses best practices for error handling, security, and consistent fetch modes.
 *
 * Usage:
 *   require 'db_connect.php';
 *   // then use $pdo for queries
 */

// -----------------------------------------------------------------------------
// 1. DATABASE CONNECTION PARAMETERS
// -----------------------------------------------------------------------------

$host    = 'localhost';    // Database host (use '127.0.0.1' or 'localhost' for local dev)
$db      = 'trea_db';      // Database name
$user    = 'root';         // Database username ('root' is default for XAMPP/WAMP)
$pass    = '';             // Database password (empty for XAMPP default, set for prod)
$charset = 'utf8mb4';      // Unicode charset (supports all emojis, languages)

// Data Source Name (DSN) tells PDO where/how to connect
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// -----------------------------------------------------------------------------
// 2. PDO CONNECTION OPTIONS
// -----------------------------------------------------------------------------

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on DB errors (safer for debugging)
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return rows as associative arrays (column => value)
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements for security
];

// -----------------------------------------------------------------------------
// 3. CONNECT TO THE DATABASE WITH ERROR HANDLING
// -----------------------------------------------------------------------------

try {
    // Create a new PDO instance for MySQL connection
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // If the connection fails, throw a detailed exception for debugging.
    // In production, you might want to log this and show a generic error page.
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
