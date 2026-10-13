CREATE TABLE IF NOT EXISTS subscription_keyword_rates (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_name    VARCHAR(100) NOT NULL DEFAULT '',
  shortcode    VARCHAR(50) NOT NULL DEFAULT '',
  keyword_name VARCHAR(100) NOT NULL,
  amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at   DATETIME NOT NULL,
  updated_at   DATETIME NOT NULL,
  UNIQUE KEY uniq_plan_shortcode_keyword (plan_name, shortcode, keyword_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT fk_subscription_transactions_user_report
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN IF NOT EXISTS subscription_plan VARCHAR(100) NULL;
ALTER TABLE subscription_keyword_rates ADD COLUMN IF NOT EXISTS plan_name VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE subscription_transactions ADD COLUMN IF NOT EXISTS plan_name VARCHAR(100) NOT NULL DEFAULT '';
