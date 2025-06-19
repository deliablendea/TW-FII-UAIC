<?php
session_start();
require_once '../../config/Database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['connected' => false]);
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT google_email, google_access_token FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$row = $stmt->fetch();

if ($row && $row['google_access_token']) {
    echo json_encode([
        'connected' => true,
        'google_email' => $row['google_email']
    ]);
} else {
    echo json_encode(['connected' => false]);
}