<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$game = trim($payload['game'] ?? '');
$allowed = ['Tic Tac Toe','Bubble Shooter','Fruit Slice','Space Shooter','Danfo Dodge'];

if (!in_array($game, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid game']);
    exit;
}

$userId = intval($_SESSION['user_id']);
$db = getDB();

$db->prepare("
    INSERT INTO game_presence (user_id, game_name, last_seen_at, created_at)
    VALUES (?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        game_name = VALUES(game_name),
        last_seen_at = NOW()
")->execute([$userId, $game]);

$db->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?")->execute([$userId]);

echo json_encode([
    'success' => true,
    'game' => $game,
    'timestamp' => date(DATE_ATOM),
]);
