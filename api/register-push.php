<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isUserLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['endpoint'])) {
    echo json_encode(['error' => 'Invalid subscription data']); exit;
}

$userId   = $_SESSION['user_id'];
$endpoint = sanitizeInput($data['endpoint'] ?? '');
$p256dh   = sanitizeInput($data['keys']['p256dh'] ?? '');
$auth     = sanitizeInput($data['keys']['auth'] ?? '');
$db = getDB();

// Upsert subscription
$stmt = $db->prepare("SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
$stmt->execute([$userId, $endpoint]);
if ($stmt->fetch()) {
    $db->prepare("UPDATE push_subscriptions SET p256dh=?, auth=? WHERE user_id=? AND endpoint=?")
       ->execute([$p256dh, $auth, $userId, $endpoint]);
} else {
    $db->prepare("INSERT INTO push_subscriptions (user_id,endpoint,p256dh,auth,created_at) VALUES (?,?,?,?,NOW())")
       ->execute([$userId, $endpoint, $p256dh, $auth]);
}

echo json_encode(['success' => true]);
