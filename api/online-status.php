<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isUserLoggedIn()) {
    echo json_encode(['online' => false]);
    exit;
}

$userId = intval($_SESSION['user_id']);
$game = trim($_GET['game'] ?? '');

if ($game === '') {
    echo json_encode(['online' => false]);
    exit;
}

$db = getDB();

$db->prepare("
    INSERT INTO game_presence (user_id, game_name, last_seen_at, created_at)
    VALUES (?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        game_name = VALUES(game_name),
        last_seen_at = NOW()
")->execute([$userId, $game]);

$stmt = $db->prepare("
    SELECT u.id, u.phone, gp.last_seen_at
    FROM game_presence gp
    JOIN users u ON u.id = gp.user_id
    WHERE gp.game_name = ?
      AND gp.user_id <> ?
      AND gp.last_seen_at > (NOW() - INTERVAL 30 SECOND)
    ORDER BY gp.last_seen_at DESC
    LIMIT 1
");
$stmt->execute([$game, $userId]);
$peer = $stmt->fetch();

echo json_encode([
    'online' => (bool) $peer,
    'userId' => $peer ? intval($peer['id']) : null,
    'phone' => $peer ? (substr($peer['phone'], 0, 4) . '****' . substr($peer['phone'], -3)) : null,
    'lastSeenAt' => $peer['last_seen_at'] ?? null,
]);
