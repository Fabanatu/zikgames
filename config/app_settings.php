<?php
require_once __DIR__ . '/database.php';

function ensureAppSettingsTable(): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $db = getDB();
    $db->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $ensured = true;
}

function getAppSetting(string $key, ?string $default = null): ?string {
    ensureAppSettingsTable();
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value === false ? $default : (string) $value;
}

function setAppSetting(string $key, string $value): void {
    ensureAppSettingsTable();
    $db = getDB();
    $db->prepare("
        INSERT INTO app_settings (setting_key, setting_value, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = NOW()
    ")->execute([$key, $value]);
}
