<?php

// end-contract.php
session_start();
require 'db_connect.php';

$claim_id = $_POST['claim_id'] ?? exit("Missing claim ID");

$stmt = $pdo->prepare("UPDATE rental_contracts SET actual_end_date = CURDATE() WHERE claim_id = ?");
$stmt->execute([$claim_id]);

header("Location: rental-management-claimed-properties.php?contract_ended=1");
?>