<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['user_id'])) {
    $db = getDB();
    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);
    $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?,?,?,NOW())")
       ->execute([$_SESSION['user_id'], 'logout', 'User logged out']);
}

session_destroy();
header('Location: /ZikGames/index.php');
exit;

