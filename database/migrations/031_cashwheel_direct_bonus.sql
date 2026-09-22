-- V21.2: diferencia avanço da meta de bônus direto em moeda na Roleta de Saque.
ALTER TABLE cashwheel_spins
  ADD COLUMN reward_type ENUM('PROGRESS','CASH_BONUS') NOT NULL DEFAULT 'PROGRESS' AFTER prize_minor,
  ADD COLUMN redemption_id CHAR(36) NULL AFTER reward_type,
  ADD INDEX idx_cashwheel_spin_reward_type (reward_type),
  ADD CONSTRAINT fk_cashwheel_spin_redemption FOREIGN KEY (redemption_id) REFERENCES promotion_redemptions(id) ON DELETE RESTRICT;
