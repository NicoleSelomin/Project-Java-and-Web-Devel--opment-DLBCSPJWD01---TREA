<?php
session_start();
require 'db_connect.php';

$count = 0;

if (isset($_SESSION['client_id'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM client_cart WHERE client_id = ?");
    $stmt->execute([$_SESSION['client_id']]);
    $count = (int)$stmt->fetchColumn();
} elseif (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $count = count($_SESSION['cart']);
}

header('Content-Type: application/json');
echo json_encode(['cartCount' => $count]);
