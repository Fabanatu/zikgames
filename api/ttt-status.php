<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/ttt-common.php';

$user = tttRequireUser();
$db = tttDb();
tttCleanupExpired($db);

$session = tttFindOpenSession($db, $user['id']);

echo json_encode([
    'success' => true,
    'session' => $session ? tttFormatSession($session, $user['id']) : null,
    'pendingInvite' => $session && $session['status'] === 'pending' && intval($session['guest_user_id']) === $user['id']
        ? tttFormatSession($session, $user['id'])
        : null,
]);
