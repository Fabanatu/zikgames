<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isUserLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$userId = $_SESSION['user_id'];
$game = trim($_GET['game'] ?? '');
$db = getDB();

if ($game !== '') {
    $stmt = $db->prepare("
        SELECT u.phone
        FROM game_presence gp
        JOIN users u ON u.id = gp.user_id
        WHERE gp.game_name = ?
          AND gp.user_id != ?
          AND gp.last_seen_at > DATE_SUB(NOW(), INTERVAL 45 SECOND)
        ORDER BY gp.last_seen_at DESC
        LIMIT 1
    ");
    $stmt->execute([$game, $userId]);
} else {
    $stmt = $db->prepare("
        SELECT phone
        FROM users
        WHERE id != ? AND status = 'active' AND updated_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY updated_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
}

$user = $stmt->fetch();

if ($user) {
    $phone = $user['phone'];
    $masked = substr($phone, 0, 4) . '****' . substr($phone, -3);
    echo json_encode(['online' => true, 'phone' => $masked, 'game' => $game ?: null]);
} else {
    echo json_encode(['online' => false, 'phone' => null, 'game' => $game ?: null]);
}
