-- V21.2: opção sem prêmio + curva de progressão da Roleta de Saque.
ALTER TABLE cashwheel_spins
  MODIFY COLUMN reward_type ENUM('PROGRESS','CASH_BONUS','NO_WIN') NOT NULL DEFAULT 'PROGRESS';

UPDATE promotion_configurations
SET config = JSON_SET(
  config,
  '$.first_spin_min_percent', COALESCE(JSON_EXTRACT(config,'$.first_spin_min_percent'), 60),
  '$.first_spin_max_percent', COALESCE(JSON_EXTRACT(config,'$.first_spin_max_percent'), 90),
  '$.later_spin_max_percent', COALESCE(JSON_EXTRACT(config,'$.later_spin_max_percent'), 8),
  '$.no_win_chance_percent', COALESCE(JSON_EXTRACT(config,'$.no_win_chance_percent'), 20)
)
WHERE type='cashwheel';
