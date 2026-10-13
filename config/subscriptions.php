<?php
require_once __DIR__ . '/database.php';

function ensureSubscriptionSchema(PDO $db): void {
    static $ready = false;
    if ($ready) {
        return;
    }

    $columns = [
        "subscription_active TINYINT(1) NOT NULL DEFAULT 0",
        "subscription_plan VARCHAR(100) NULL",
        "subscription_start_at DATETIME NULL",
        "subscription_end_at DATETIME NULL",
        "subscription_shortcode VARCHAR(50) NULL",
        "subscription_keyword VARCHAR(100) NULL",
        "subscription_updated_at DATETIME NULL",
    ];

    foreach ($columns as $columnSql) {
        $parts = explode(' ', $columnSql, 2);
        $columnName = $parts[0];
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE " . $db->quote($columnName));
        if (!$stmt || !$stmt->fetch()) {
            $db->exec("ALTER TABLE users ADD COLUMN $columnSql");
        }
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS subscription_keyword_rates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plan_name VARCHAR(100) NOT NULL DEFAULT '',
            shortcode VARCHAR(50) NOT NULL DEFAULT '',
            keyword_name VARCHAR(100) NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_plan_shortcode_keyword (plan_name, shortcode, keyword_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS subscription_transactions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            phone VARCHAR(20) NOT NULL,
            plan_name VARCHAR(100) NOT NULL DEFAULT '',
            shortcode VARCHAR(50) NOT NULL DEFAULT '',
            keyword_name VARCHAR(100) NOT NULL DEFAULT '',
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            active TINYINT(1) NOT NULL DEFAULT 0,
            subscription_start_at DATETIME NULL,
            subscription_end_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_subscription_transactions_phone (phone),
            INDEX idx_subscription_transactions_created (created_at),
            INDEX idx_subscription_transactions_keyword (keyword_name),
            CONSTRAINT fk_subscription_transactions_user
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $tableColumns = [
        'subscription_keyword_rates' => [
            "plan_name VARCHAR(100) NOT NULL DEFAULT ''",
        ],
        'subscription_transactions' => [
            "plan_name VARCHAR(100) NOT NULL DEFAULT ''",
        ],
    ];

    foreach ($tableColumns as $tableName => $tableDefs) {
        foreach ($tableDefs as $columnSql) {
            $parts = explode(' ', $columnSql, 2);
            $columnName = $parts[0];
            $stmt = $db->query("SHOW COLUMNS FROM {$tableName} LIKE " . $db->quote($columnName));
            if (!$stmt || !$stmt->fetch()) {
                $db->exec("ALTER TABLE {$tableName} ADD COLUMN {$columnSql}");
            }
        }
    }

    $ready = true;
}

function normalizeSubscriptionDate(?string $value): ?string {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function computeSubscriptionState(array $user): array {
    $activeFlag = intval($user['subscription_active'] ?? 0) === 1;
    $startAt = $user['subscription_start_at'] ?? null;
    $endAt = $user['subscription_end_at'] ?? null;
    $now = time();
    $startTs = $startAt ? strtotime($startAt) : null;
    $endTs = $endAt ? strtotime($endAt) : null;

    $isExpired = $endTs !== null && $endTs < $now;
    $isPending = $startTs !== null && $startTs > $now;
    $isActive = $activeFlag && !$isExpired && !$isPending && $endTs !== null;

    if ($isActive) {
        $message = 'Subscription active';
    } elseif ($isExpired) {
        $message = 'Your subscription has expired.';
    } elseif ($isPending) {
        $message = 'Your subscription is not active yet.';
    } else {
        $message = 'You need an active subscription before playing.';
    }

    return [
        'active' => $isActive,
        'expired' => $isExpired,
        'pending' => $isPending,
        'message' => $message,
        'start_at' => $startAt,
        'end_at' => $endAt,
    ];
}

function getPhoneSubscriptionStatus(string $phone): array {
    $db = getDB();
    ensureSubscriptionSchema($db);

    $stmt = $db->prepare("
        SELECT id, phone, subscription_active, subscription_start_at, subscription_end_at, subscription_shortcode, subscription_keyword
             , subscription_plan
        FROM users
        WHERE phone = ?
        LIMIT 1
    ");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if (!$user) {
        return [
            'found' => false,
            'active' => false,
            'expired' => false,
            'pending' => false,
            'message' => 'You need an active subscription before playing.',
            'start_at' => null,
            'end_at' => null,
        ];
    }

    $status = computeSubscriptionState($user);

    if ($status['expired'] && intval($user['subscription_active']) === 1) {
        $db->prepare("
            UPDATE users
            SET subscription_active = 0, subscription_updated_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([$user['id']]);
        $user['subscription_active'] = 0;
        $status = computeSubscriptionState($user);
    }

    return array_merge(['found' => true, 'user_id' => intval($user['id'])], $status);
}

function upsertUserSubscription(
    string $phone,
    int $active,
    ?string $startAt,
    ?string $endAt,
    string $shortcode = '',
    string $keyword = '',
    string $planName = ''
): array {
    $db = getDB();
    ensureSubscriptionSchema($db);

    $stmt = $db->prepare("SELECT id, status FROM users WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if (!$user) {
        $db->prepare("
            INSERT INTO users (
                phone, status, last_login, total_games_played, created_at, updated_at,
                subscription_active, subscription_plan, subscription_start_at, subscription_end_at,
                subscription_shortcode, subscription_keyword, subscription_updated_at
            ) VALUES (?, 'active', NULL, 0, NOW(), NOW(), ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$phone, $active, $planName, $startAt, $endAt, $shortcode, $keyword]);
        $userId = intval($db->lastInsertId());
    } else {
        $db->prepare("
            UPDATE users
            SET subscription_active = ?,
                subscription_plan = ?,
                subscription_start_at = ?,
                subscription_end_at = ?,
                subscription_shortcode = ?,
                subscription_keyword = ?,
                subscription_updated_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$active, $planName, $startAt, $endAt, $shortcode, $keyword, $user['id']]);
        $userId = intval($user['id']);
    }

    return [
        'user_id' => $userId,
        'phone' => $phone,
        'subscription_active' => $active,
        'subscription_plan' => $planName,
        'subscription_start_at' => $startAt,
        'subscription_end_at' => $endAt,
        'subscription_shortcode' => $shortcode,
        'subscription_keyword' => $keyword,
    ];
}

function getKeywordAmount(string $keyword, string $shortcode = '', string $planName = ''): float {
    $db = getDB();
    ensureSubscriptionSchema($db);

    $stmt = $db->prepare("
        SELECT amount
        FROM subscription_keyword_rates
        WHERE plan_name = ? AND shortcode = ? AND keyword_name = ?
        LIMIT 1
    ");
    $stmt->execute([$planName, $shortcode, $keyword]);
    $amount = $stmt->fetchColumn();

    if ($amount !== false) {
        return floatval($amount);
    }

    $planFallback = $db->prepare("
        SELECT amount
        FROM subscription_keyword_rates
        WHERE plan_name = ? AND shortcode = '' AND keyword_name = ?
        LIMIT 1
    ");
    $planFallback->execute([$planName, $keyword]);
    $amount = $planFallback->fetchColumn();

    if ($amount !== false) {
        return floatval($amount);
    }

    $fallback = $db->prepare("
        SELECT amount
        FROM subscription_keyword_rates
        WHERE plan_name = '' AND shortcode = ? AND keyword_name = ?
        LIMIT 1
    ");
    $fallback->execute([$shortcode, $keyword]);
    $amount = $fallback->fetchColumn();

    if ($amount !== false) {
        return floatval($amount);
    }

    $globalFallback = $db->prepare("
        SELECT amount
        FROM subscription_keyword_rates
        WHERE plan_name = '' AND shortcode = '' AND keyword_name = ?
        LIMIT 1
    ");
    $globalFallback->execute([$keyword]);
    $amount = $globalFallback->fetchColumn();

    return $amount === false ? 0.0 : floatval($amount);
}

function setKeywordAmount(string $keyword, float $amount, string $shortcode = '', string $planName = ''): void {
    $db = getDB();
    ensureSubscriptionSchema($db);

    $db->prepare("
        INSERT INTO subscription_keyword_rates (plan_name, shortcode, keyword_name, amount, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            amount = VALUES(amount),
            updated_at = NOW()
    ")->execute([$planName, $shortcode, $keyword, $amount]);
}

function getKeywordRates(): array {
    $db = getDB();
    ensureSubscriptionSchema($db);
    return $db->query("
        SELECT id, plan_name, shortcode, keyword_name, amount, updated_at
        FROM subscription_keyword_rates
        ORDER BY plan_name ASC, keyword_name ASC, shortcode ASC
    ")->fetchAll();
}

function logSubscriptionTransaction(
    ?int $userId,
    string $phone,
    string $planName,
    string $shortcode,
    string $keyword,
    float $amount,
    int $active,
    ?string $startAt,
    ?string $endAt
): void {
    $db = getDB();
    ensureSubscriptionSchema($db);

    $db->prepare("
        INSERT INTO subscription_transactions (
            user_id, phone, plan_name, shortcode, keyword_name, amount, active,
            subscription_start_at, subscription_end_at, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([$userId, $phone, $planName, $shortcode, $keyword, $amount, $active, $startAt, $endAt]);
}
