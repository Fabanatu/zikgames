<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

function queueAdminNotification(string $title, string $message, string $targetType = 'all', string $targetVal = ''): array {
    $title = sanitizeInput($title);
    $message = sanitizeInput($message);
    $targetType = sanitizeInput($targetType);
    $targetVal = sanitizeInput($targetVal);

    if ($title === '' || $message === '') {
        return ['success' => false, 'error' => 'Title and message required'];
    }

    $db = getDB();

    if ($targetType === 'all') {
        $stmt = $db->query("SELECT endpoint,p256dh,auth FROM push_subscriptions");
    } elseif ($targetType === 'phone') {
        $stmt = $db->prepare("
            SELECT ps.endpoint, ps.p256dh, ps.auth
            FROM push_subscriptions ps
            JOIN users u ON ps.user_id = u.id
            WHERE u.phone = ?
        ");
        $stmt->execute([$targetVal]);
    } elseif ($targetType === 'game') {
        $stmt = $db->prepare("
            SELECT DISTINCT ps.endpoint, ps.p256dh, ps.auth
            FROM push_subscriptions ps
            JOIN users u ON ps.user_id = u.id
            JOIN game_scores gs ON gs.user_id = u.id
            WHERE gs.game_name = ?
        ");
        $stmt->execute([$targetVal]);
    } else {
        return ['success' => false, 'error' => 'Invalid target type'];
    }

    $subs = $stmt->fetchAll();
    $sentCount = 0;

    foreach ($subs as $sub) {
        $sentCount++;
    }

    $db->prepare("
        INSERT INTO notifications (title, message, target_type, target_value, sent_count, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ")->execute([$title, $message, $targetType, $targetVal, $sentCount]);

    return ['success' => true, 'sent' => $sentCount];
}
