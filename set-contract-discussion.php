<?php
session_start();
require 'db_connect.php';
if (!isset($_SESSION['staff_id'])) die('No access.');

$claim_id = intval($_POST['claim_id']);
$datetime = $_POST['contract_discussion_datetime'] ?? '';

if (!$datetime) die('No datetime provided.');

// Check if rental_contract row exists
$stmt = $pdo->prepare("SELECT contract_id FROM rental_contracts WHERE claim_id = ?");
$stmt->execute([$claim_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if ($contract) {
    // Update existing row
    $stmt = $pdo->prepare("UPDATE rental_contracts SET contract_discussion_datetime = ? WHERE claim_id = ?");
    $stmt->execute([$datetime, $claim_id]);
} else {
    // Insert new contract row with datetime
    $stmt = $pdo->prepare("INSERT INTO rental_contracts (claim_id, contract_discussion_datetime) VALUES (?, ?)");
    $stmt->execute([$claim_id, $datetime]);
}

header("Location: rental-management-claimed-properties.php");
exit;
?>
