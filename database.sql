-- ================================================
-- FabGames Nigeria – Database Schema
-- Run this in phpMyAdmin or MySQL CLI
-- ================================================

CREATE DATABASE IF NOT EXISTS fabgames CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fabgames;

-- ── Users ──
CREATE TABLE IF NOT EXISTS users (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone             VARCHAR(20) NOT NULL UNIQUE,
  status            ENUM('active','disabled') NOT NULL DEFAULT 'active',
  subscription_active TINYINT(1) NOT NULL DEFAULT 0,
  subscription_plan VARCHAR(100) NULL,
  subscription_start_at DATETIME NULL,
  subscription_end_at DATETIME NULL,
  subscription_shortcode VARCHAR(50) NULL,
  subscription_keyword VARCHAR(100) NULL,
  subscription_updated_at DATETIME NULL,
  last_login        DATETIME,
  total_games_played INT UNSIGNED NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL,
  updated_at        DATETIME NOT NULL,
  INDEX idx_phone  (phone),
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- ── Game Scores ──
CREATE TABLE IF NOT EXISTS game_scores (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  game_name  VARCHAR(50) NOT NULL,
  score      INT UNSIGNED NOT NULL DEFAULT 0,
  duration   SMALLINT UNSIGNED,
  difficulty_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  game_mode  ENUM('single','multiplayer') NOT NULL DEFAULT 'single',
  survival_run TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_user_id  (user_id),
  INDEX idx_game     (game_name),
  INDEX idx_created  (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS game_presence (
  user_id      INT UNSIGNED NOT NULL PRIMARY KEY,
  game_name    VARCHAR(50) NOT NULL,
  last_seen_at DATETIME NOT NULL,
  created_at   DATETIME NOT NULL,
  INDEX idx_presence_game (game_name, last_seen_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ttt_sessions (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  host_user_id         INT UNSIGNED NOT NULL,
  guest_user_id        INT UNSIGNED NOT NULL,
  status               ENUM('pending','active','completed','declined','expired','cancelled') NOT NULL DEFAULT 'pending',
  board_state          CHAR(9) NOT NULL DEFAULT '---------',
  current_turn_user_id INT UNSIGNED NULL,
  winner_user_id       INT UNSIGNED NULL,
  result               ENUM('host_win','guest_win','draw') NULL,
  host_symbol          CHAR(1) NOT NULL DEFAULT 'X',
  guest_symbol         CHAR(1) NOT NULL DEFAULT 'O',
  responded_at         DATETIME NULL,
  completed_at         DATETIME NULL,
  created_at           DATETIME NOT NULL,
  updated_at           DATETIME NOT NULL,
  INDEX idx_ttt_host_status (host_user_id, status),
  INDEX idx_ttt_guest_status (guest_user_id, status),
  INDEX idx_ttt_updated (updated_at),
  FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (guest_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (current_turn_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (winner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Push Subscriptions ──
CREATE TABLE IF NOT EXISTS push_subscriptions (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  endpoint   TEXT NOT NULL,
  p256dh     TEXT,
  auth       VARCHAR(100),
  created_at DATETIME NOT NULL,
  INDEX idx_user_id (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Notifications ──
CREATE TABLE IF NOT EXISTS notifications (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(200) NOT NULL,
  message      TEXT NOT NULL,
  target_type  ENUM('all','phone','game') NOT NULL DEFAULT 'all',
  target_value VARCHAR(100),
  sent_count   INT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL,
  INDEX idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subscription_keyword_rates (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_name    VARCHAR(100) NOT NULL DEFAULT '',
  shortcode    VARCHAR(50) NOT NULL DEFAULT '',
  keyword_name VARCHAR(100) NOT NULL,
  amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at   DATETIME NOT NULL,
  updated_at   DATETIME NOT NULL,
  UNIQUE KEY uniq_plan_shortcode_keyword (plan_name, shortcode, keyword_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subscription_transactions (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id               INT UNSIGNED NULL,
  phone                 VARCHAR(20) NOT NULL,
  plan_name             VARCHAR(100) NOT NULL DEFAULT '',
  shortcode             VARCHAR(50) NOT NULL DEFAULT '',
  keyword_name          VARCHAR(100) NOT NULL DEFAULT '',
  amount                DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  active                TINYINT(1) NOT NULL DEFAULT 0,
  subscription_start_at DATETIME NULL,
  subscription_end_at   DATETIME NULL,
  created_at            DATETIME NOT NULL,
  INDEX idx_subscription_tx_phone (phone),
  INDEX idx_subscription_tx_created (created_at),
  INDEX idx_subscription_tx_keyword (keyword_name),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Admins ──
CREATE TABLE IF NOT EXISTS admins (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_email (email)
) ENGINE=InnoDB;

-- ── Activity Logs ──
CREATE TABLE IF NOT EXISTS activity_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED,
  action     VARCHAR(50) NOT NULL,
  details    TEXT,
  created_at DATETIME NOT NULL,
  INDEX idx_user_id (user_id),
  INDEX idx_action  (action),
  INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ================================================
-- Sample Data (Optional - remove in production)
-- ================================================
-- INSERT INTO users (phone,status,last_login,total_games_played,created_at,updated_at)
-- VALUES ('08012345678','active',NOW(),5,NOW(),NOW()),
--        ('08098765432','active',NOW(),3,NOW(),NOW()),
--        ('07034567890','active',NOW(),8,NOW(),NOW());
