<?php
/*
|--------------------------------------------------------------------------
| add-service.php
|--------------------------------------------------------------------------
| Handles submission of new services for the TREA platform.
| Receives POST data, generates slug, inserts into `services` table,
| then redirects to the services dashboard.
*/

// Include database connection
require 'db_connect.php';

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and trim the submitted service name and description
    $name = trim($_POST['service_name']);
    $description = trim($_POST['description']);

    // Generate a URL-friendly slug from the service name
    // Replaces non-alphanumeric characters with underscore and makes lowercase
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '_', $name)));

    try {
        // Prepare the SQL statement to insert new service
        $stmt = $pdo->prepare("INSERT INTO services (service_name, description, slug) VALUES (?, ?, ?)");
        // Execute the statement with provided values
        $stmt->execute([$name, $description, $slug]);
        // Redirect to services dashboard upon success
        header("Location: services-dashboard.php"); // Adjust destination as needed
        exit();
    } catch (PDOException $e) {
        // If there is a database error, output the error message and stop execution
        die("Database error: " . $e->getMessage());
    }
}
?>
