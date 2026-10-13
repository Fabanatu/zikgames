<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/subscriptions.php';

function requireUserLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['phone'])) {
        header('Location: /ZikGames/login.php');
        exit;
    }
    
    // Check status
    try {
        $db = getDB();
        ensureSubscriptionSchema($db);
        $stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $status = $stmt->fetchColumn();
        if ($status === 'disabled') {
            session_destroy();
            header('Location: /ZikGames/login.php?error=disabled');
            exit;
        }

        $subscription = getPhoneSubscriptionStatus(strval($_SESSION['phone']));
        if (!$subscription['active']) {
            session_destroy();
            $errorCode = $subscription['expired'] ? 'expired' : 'inactive';
            header('Location: /ZikGames/login.php?error=' . $errorCode);
            exit;
        }
    } catch (Exception $e) {
        // Fallback silently if DB error during check
    }
}

function requireAdminLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['admin_id'])) {
        header('Location: /ZikGames/admin/login.php');
        exit;
    }
}

function isUserLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && isset($_SESSION['phone']);
}

function isAdminLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_id']);
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function validateNigerianPhone($phone) {
    $phone = preg_replace('/\s+/', '', $phone);
    return preg_match('/^0[789][01]\d{8}$/', $phone);
}

function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

