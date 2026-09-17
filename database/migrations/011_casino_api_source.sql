-- A origem de jogos antigos permanece MANUAL para não atribuir uma API sem confirmação.
ALTER TABLE casino_providers ADD COLUMN api_source VARCHAR(30) NOT NULL DEFAULT 'MANUAL' AFTER mode;
