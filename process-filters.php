<?php
/*
|--------------------------------------------------------------------------
| process-filters.php
|--------------------------------------------------------------------------
| Processes user-defined filters for property searches, constructing
| dynamic SQL queries based on provided criteria (search keyword,
| property type, listing type, and price range).
|
| Usage:
| Include this file in property listing pages to apply consistent
| filtering logic across the platform.
|
| Standards:
| - Secure handling of SQL queries using PDO prepared statements
|--------------------------------------------------------------------------
*/

// Initialize arrays to store SQL conditions and parameters
$filterConditions = [];
$filterParams = [];

// Handle keyword-based searches for property name, location, property type, or listing type
if (!empty($_GET['search'])) {
    $filterConditions[] = "(p.property_name LIKE ? OR p.location LIKE ? OR p.property_type LIKE ? OR p.listing_type LIKE ?)";
    $searchTerm = '%' . $_GET['search'] . '%';
    // Add the search term 4 times for the 4 placeholders
    $filterParams[] = $searchTerm;
    $filterParams[] = $searchTerm;
    $filterParams[] = $searchTerm;
    $filterParams[] = $searchTerm;
}

// Filter by property type (e.g., apartment, house, etc.)
if (!empty($_GET['property_type'])) {
    $filterConditions[] = "p.property_type = ?";
    $filterParams[] = $_GET['property_type'];
}

// Filter by listing type (rent or sale)
if (!empty($_GET['listing_type'])) {
    $filterConditions[] = "p.listing_type = ?";
    $filterParams[] = $_GET['listing_type'];
}

// Apply minimum price filter
if (!empty($_GET['min_price'])) {
    $filterConditions[] = "p.price >= ?";
    $filterParams[] = $_GET['min_price'];
}

// Apply maximum price filter
if (!empty($_GET['max_price'])) {
    $filterConditions[] = "p.price <= ?";
    $filterParams[] = $_GET['max_price'];
}

// Construct SQL WHERE clause from accumulated filters
$whereSql = $filterConditions ? ' AND ' . implode(' AND ', $filterConditions) : '';

// Fetch properties only if the current page does not have custom fetch logic
if (!isset($skipPropertyFetch) || !$skipPropertyFetch) {
    $filterQuery = "SELECT * FROM properties $whereSql ORDER BY created_at DESC";
    $stmt = $pdo->prepare($filterQuery);
    $stmt->execute($filterParams);
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
