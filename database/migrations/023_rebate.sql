-- V16: rebate calculado somente sobre apostas PlayFiver confirmadas após ativação.
-- Desabilitado até configuração explícita no Admin. Frações de centavo são preservadas.
CREATE TABLE IF NOT EXISTS rebate_settings (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 activated_at DATETIME(6) NULL,
 min_claim_minor BIGINT UNSIGNED NOT NULL DEFAULT 100,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO rebate_settings(id,enabled,min_claim_minor) VALUES(1,0,100);
CREATE TABLE IF NOT EXISTS rebate_accounts (
 user_id CHAR(36) NOT NULL PRIMARY KEY,
 activation_epoch DATETIME(6) NOT NULL,
 volume_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 pending_units BIGINT UNSIGNED NOT NULL DEFAULT 0,
 claimed_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_rebate_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS rebate_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 bet_ledger_id CHAR(36) NOT NULL,
 user_id CHAR(36) NOT NULL,
 campaign_id BIGINT UNSIGNED NULL,
 volume_minor BIGINT UNSIGNED NOT NULL,
 rate_basis_points SMALLINT UNSIGNED NOT NULL,
 reward_units BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_rebate_bet (bet_ledger_id),
 INDEX idx_rebate_user (user_id,created_at),
 CONSTRAINT fk_rebate_bet FOREIGN KEY (bet_ledger_id) REFERENCES ledger_entries(id) ON DELETE RESTRICT,
 CONSTRAINT fk_rebate_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_rebate_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS rebate_claims (
 id CHAR(36) NOT NULL PRIMARY KEY,
 user_id CHAR(36) NOT NULL,
 amount_minor BIGINT UNSIGNED NOT NULL,
 financial_transaction_id CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 INDEX idx_rebate_claim_user (user_id,created_at),
 CONSTRAINT fk_rebate_claim_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_rebate_claim_tx FOREIGN KEY (financial_transaction_id) REFERENCES financial_transactions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
