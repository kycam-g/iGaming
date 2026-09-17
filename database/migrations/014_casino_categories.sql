ALTER TABLE casino_games
  MODIFY COLUMN category VARCHAR(60) NOT NULL DEFAULT 'SLOTS';

CREATE TABLE IF NOT EXISTS casino_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  icon_key VARCHAR(30) NOT NULL DEFAULT 'slots',
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  INDEX idx_casino_categories_public (enabled,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO casino_categories(code,name,icon_key,enabled,sort_order) VALUES
('SLOTS','Slots','slots',1,10),
('OTHER','Pescaria','fish',1,20),
('LIVE','SportBet','sport',1,30),
('TABLE','Roleta','roulette',1,40);
