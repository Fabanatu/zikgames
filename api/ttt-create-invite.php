<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/ttt-common.php';

$user = tttRequireUser();
$db = tttDb();
tttCleanupExpired($db);

$existing = tttFindOpenSession($db, $user['id']);
if ($existing) {
    echo json_encode([
        'success' => true,
        'session' => tttFormatSession($existing, $user['id']),
        'message' => 'You already have an open Tic Tac Toe session.',
    ]);
    exit;
}

$stmt = $db->prepare("
    SELECT u.id, u.phone
    FROM game_presence gp
    JOIN users u ON u.id = gp.user_id
    WHERE gp.game_name = 'Tic Tac Toe'
      AND gp.user_id <> ?
      AND gp.last_seen_at > (NOW() - INTERVAL 30 SECOND)
    ORDER BY gp.last_seen_at DESC
    LIMIT 1
");
$stmt->execute([$user['id']]);
$opponent = $stmt->fetch();

if (!$opponent) {
    http_response_code(404);
    echo json_encode(['error' => 'No friend is online right now.']);
    exit;
}

$conflictStmt = $db->prepare("
    SELECT id
    FROM ttt_sessions
    WHERE status IN ('pending', 'active')
      AND (
        host_user_id = ?
        OR guest_user_id = ?
        OR host_user_id = ?
        OR guest_user_id = ?
      )
    LIMIT 1
");
$conflictStmt->execute([$user['id'], $user['id'], intval($opponent['id']), intval($opponent['id'])]);

if ($conflictStmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'That player is already busy in another Tic Tac Toe session.']);
    exit;
}

$db->prepare("
    INSERT INTO ttt_sessions (
        host_user_id,
        guest_user_id,
        status,
        board_state,
        current_turn_user_id,
        host_symbol,
        guest_symbol,
        created_at,
        updated_at
    ) VALUES (?, ?, 'pending', '---------', NULL, 'X', 'O', NOW(), NOW())
")->execute([$user['id'], intval($opponent['id'])]);

$sessionId = intval($db->lastInsertId());
$session = tttFindOpenSession($db, $user['id']);

$db->prepare("
    INSERT INTO activity_logs (user_id, action, details, created_at)
    VALUES (?, 'ttt_invite_sent', ?, NOW())
")->execute([
    $user['id'],
    'Tic Tac Toe invite sent to user #' . intval($opponent['id']),
]);

echo json_encode([
    'success' => true,
    'sessionId' => $sessionId,
    'session' => $session ? tttFormatSession($session, $user['id']) : null,
    'message' => 'Invite sent to ' . tttMaskPhone($opponent['phone']) . '.',
]);
