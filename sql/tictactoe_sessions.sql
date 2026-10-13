USE fabgames;

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
  CONSTRAINT fk_ttt_host FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ttt_guest FOREIGN KEY (guest_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ttt_turn FOREIGN KEY (current_turn_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ttt_winner FOREIGN KEY (winner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
