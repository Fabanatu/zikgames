<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$allowed = ['Tic Tac Toe', 'Bubble Shooter', 'Fruit Slice', 'Space Shooter', 'Danfo Dodge'];
$game = trim($_GET['game'] ?? 'all');
$limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
$includeUserRank = isset($_GET['includeUserRank']) ? (bool) intval($_GET['includeUserRank']) : true;
$currentUserId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

if ($game !== 'all' && !in_array($game, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid game']);
    exit;
}

$db = getDB();
$params = [];
$whereSql = '';

if ($game !== 'all') {
    $whereSql = 'WHERE gs.game_name = ?';
    $params[] = $game;
}

$stmt = $db->prepare("
    SELECT
        gs.user_id,
        u.phone,
        " . ($game === 'all' ? "'All Games'" : 'gs.game_name') . " AS game_name,
        MAX(gs.score) AS best_score,
        MAX(gs.difficulty_level) AS top_level,
        MAX(gs.multiplier) AS top_multiplier,
        MAX(gs.created_at) AS last_played_at
    FROM game_scores gs
    JOIN users u ON gs.user_id = u.id
    $whereSql
    GROUP BY gs.user_id, " . ($game === 'all' ? 'u.phone' : 'gs.game_name, u.phone') . "
    ORDER BY best_score DESC, last_played_at ASC
");
$stmt->execute($params);

$allRows = $stmt->fetchAll();
$rankedRows = [];

foreach ($allRows as $index => $row) {
    $rankedRows[] = [
        'rank' => $index + 1,
        'user_id' => intval($row['user_id']),
        'phone' => substr($row['phone'], 0, 4) . '****' . substr($row['phone'], -3),
        'game_name' => $row['game_name'],
        'best_score' => intval($row['best_score']),
        'top_level' => intval($row['top_level']),
        'top_multiplier' => (float) $row['top_multiplier'],
        'last_played_at' => $row['last_played_at'],
        'is_current_user' => $currentUserId > 0 && intval($row['user_id']) === $currentUserId,
    ];
}

$response = [
    'game' => $game,
    'top' => array_slice($rankedRows, 0, $limit),
];

if ($includeUserRank && $currentUserId > 0) {
    $response['currentUser'] = null;
    foreach ($rankedRows as $row) {
        if ($row['user_id'] === $currentUserId) {
            $response['currentUser'] = $row;
            break;
        }
    }
}

echo json_encode($response);
