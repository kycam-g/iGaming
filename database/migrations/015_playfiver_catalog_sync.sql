ALTER TABLE casino_games
  ADD COLUMN api_source VARCHAR(30) NOT NULL DEFAULT 'MANUAL' AFTER access_count,
  ADD COLUMN source_type VARCHAR(60) NULL AFTER api_source,
  ADD COLUMN source_distribution VARCHAR(60) NULL AFTER source_type,
  ADD COLUMN source_original VARCHAR(20) NULL AFTER source_distribution,
  ADD COLUMN last_synced_at DATETIME(6) NULL AFTER source_original,
  ADD INDEX idx_casino_games_api_source (api_source, enabled);
