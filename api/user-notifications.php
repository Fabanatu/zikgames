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

$userId = intval($_SESSION['user_id']);
$phone = strval($_SESSION['phone']);
$db = getDB();
$limit = min(10, max(1, intval($_GET['limit'] ?? 5)));

$stmt = $db->prepare("
    SELECT
        n.id,
        n.title,
        n.message,
        n.target_type,
        n.target_value,
        n.created_at
    FROM notifications n
    WHERE n.target_type = 'all'
       OR (n.target_type = 'phone' AND n.target_value = ?)
       OR (
            n.target_type = 'game'
            AND EXISTS (
                SELECT 1
                FROM game_scores gs
                WHERE gs.user_id = ?
                  AND gs.game_name = n.target_value
            )
       )
    ORDER BY n.created_at DESC
    LIMIT $limit
");
$stmt->execute([$phone, $userId]);
$rows = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'notifications' => $rows,
]);
