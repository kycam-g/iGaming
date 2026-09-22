-- V19: giro validado pelo servidor; origem única por depósito pago ou cadastro indicado.
-- Ativação é registrada apenas ao publicar a campanha: eventos anteriores NÃO geram giros.
CREATE TABLE IF NOT EXISTS roulette_campaign_state (
 campaign_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 activated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_roulette_state_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS roulette_spin_credits (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 campaign_id BIGINT UNSIGNED NOT NULL,
 user_id CHAR(36) NOT NULL,
 source_type ENUM('DEPOSIT','REFERRAL') NOT NULL,
 source_id CHAR(36) NOT NULL,
 total_spins TINYINT UNSIGNED NOT NULL,
 used_spins TINYINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_roulette_credit_source (campaign_id,user_id,source_type,source_id),
 INDEX idx_roulette_credit_user (user_id,campaign_id),
 CONSTRAINT fk_roulette_credit_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_roulette_credit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS roulette_spins (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 credit_id BIGINT UNSIGNED NOT NULL,
 user_id CHAR(36) NOT NULL,
 campaign_id BIGINT UNSIGNED NOT NULL,
 redemption_id CHAR(36) NOT NULL,
 prize_minor BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_roulette_spin_redemption (redemption_id),
 INDEX idx_roulette_spin_campaign (campaign_id,created_at),
 CONSTRAINT fk_roulette_spin_credit FOREIGN KEY (credit_id) REFERENCES roulette_spin_credits(id) ON DELETE RESTRICT,
 CONSTRAINT fk_roulette_spin_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_roulette_spin_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_roulette_spin_redemption FOREIGN KEY (redemption_id) REFERENCES promotion_redemptions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Campanhas já habilitadas só entram em vigor agora; não há créditos retroativos.
INSERT IGNORE INTO roulette_campaign_state(campaign_id,activated_at)
 SELECT id,NOW(6) FROM promotion_configurations WHERE type='roulette' AND enabled=1;
