ALTER TABLE casino_games
  ADD COLUMN access_count INT NOT NULL DEFAULT 0 AFTER sort_order;
