<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/ttt-common.php';

$user = tttRequireUser();
$db = tttDb();
tttCleanupExpired($db);

$payload = json_decode(file_get_contents('php://input'), true);
$sessionId = intval($payload['sessionId'] ?? 0);
$action = trim(strtolower($payload['action'] ?? ''));

if ($sessionId <= 0 || !in_array($action, ['accept', 'decline'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid invite response.']);
    exit;
}

$stmt = $db->prepare("
    SELECT *
    FROM ttt_sessions
    WHERE id = ?
      AND guest_user_id = ?
      AND status = 'pending'
    LIMIT 1
");
$stmt->execute([$sessionId, $user['id']]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(404);
    echo json_encode(['error' => 'Invite not found or no longer available.']);
    exit;
}

if ($action === 'decline') {
    $db->prepare("
        UPDATE ttt_sessions
        SET status = 'declined', responded_at = NOW(), updated_at = NOW()
        WHERE id = ?
    ")->execute([$sessionId]);

    echo json_encode([
        'success' => true,
        'status' => 'declined',
    ]);
    exit;
}

$db->prepare("
    UPDATE ttt_sessions
    SET
        status = 'active',
        current_turn_user_id = host_user_id,
        responded_at = NOW(),
        updated_at = NOW()
    WHERE id = ?
")->execute([$sessionId]);

$active = tttFindOpenSession($db, $user['id']);

echo json_encode([
    'success' => true,
    'status' => 'active',
    'session' => $active ? tttFormatSession($active, $user['id']) : null,
]);
