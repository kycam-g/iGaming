-- V15: comissão de indicações diretas. Nenhuma comissão retroativa na ativação.
CREATE TABLE IF NOT EXISTS agency_settings (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 activated_at DATETIME(6) NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO agency_settings(id,enabled) VALUES(1,0);
CREATE TABLE IF NOT EXISTS agency_commissions (
 id CHAR(36) NOT NULL PRIMARY KEY,
 bet_ledger_id CHAR(36) NOT NULL,
 referrer_user_id CHAR(36) NOT NULL,
 referred_user_id CHAR(36) NOT NULL,
 campaign_id BIGINT UNSIGNED NOT NULL,
 bet_minor BIGINT UNSIGNED NOT NULL,
 commission_percent DECIMAL(7,2) NOT NULL,
 amount_minor BIGINT UNSIGNED NOT NULL,
 financial_transaction_id CHAR(36) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_agency_bet_ledger (bet_ledger_id),
 INDEX idx_agency_referrer (referrer_user_id,created_at),
 CONSTRAINT fk_agency_bet FOREIGN KEY(bet_ledger_id) REFERENCES ledger_entries(id) ON DELETE RESTRICT,
 CONSTRAINT fk_agency_referrer FOREIGN KEY(referrer_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_agency_referred FOREIGN KEY(referred_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_agency_campaign FOREIGN KEY(campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_agency_transaction FOREIGN KEY(financial_transaction_id) REFERENCES financial_transactions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
