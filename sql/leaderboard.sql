-- SQL script for ZikGames enhancements

-- Extend game_scores with additional columns for difficulty, multiplier, game mode, and survival run
ALTER TABLE game_scores
  ADD COLUMN difficulty_level TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER duration,
  ADD COLUMN multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00 AFTER difficulty_level,
  ADD COLUMN game_mode ENUM('single','multiplayer') NOT NULL DEFAULT 'single' AFTER multiplier,
  ADD COLUMN survival_run TINYINT(1) NOT NULL DEFAULT 0 AFTER game_mode;

-- Table to track online presence for multiplayer availability checks
CREATE TABLE IF NOT EXISTS game_presence (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  game_name VARCHAR(50) NOT NULL,
  last_seen_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_presence_game (game_name, last_seen_at)
) ENGINE=InnoDB;

-- Leaderboard table to store top scores per game
CREATE TABLE IF NOT EXISTS leaderboard (
  id INT AUTO_INCREMENT PRIMARY KEY,
  game VARCHAR(100) NOT NULL,
  user_id VARCHAR(50) NULL,
  score INT NOT NULL,
  difficulty_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  game_mode ENUM('single','multiplayer') NOT NULL DEFAULT 'single',
  survival_run TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
