-- V12: códigos privados e resgates auditáveis. Não altera usuários ou senhas.
-- Idempotente: se uma execução anterior parou no CREATE TABLE, não repetir o ALTER/INDEX.
SET @coupon_column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promotion_configurations' AND COLUMN_NAME = 'coupon_code');
SET @coupon_column_ddl = IF(@coupon_column_exists = 0, 'ALTER TABLE promotion_configurations ADD COLUMN coupon_code VARCHAR(64) NULL', 'DO 0');
PREPARE coupon_column_stmt FROM @coupon_column_ddl;
EXECUTE coupon_column_stmt;
DEALLOCATE PREPARE coupon_column_stmt;
SET @coupon_index_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promotion_configurations' AND INDEX_NAME = 'uq_promotion_coupon_code');
SET @coupon_index_ddl = IF(@coupon_index_exists = 0, 'CREATE UNIQUE INDEX uq_promotion_coupon_code ON promotion_configurations (coupon_code)', 'DO 0');
PREPARE coupon_index_stmt FROM @coupon_index_ddl;
EXECUTE coupon_index_stmt;
DEALLOCATE PREPARE coupon_index_stmt;
CREATE TABLE IF NOT EXISTS promotion_redemptions (
 id CHAR(36) PRIMARY KEY,
 user_id CHAR(36) NOT NULL,
 campaign_id BIGINT UNSIGNED NOT NULL,
 coupon_campaign_id BIGINT UNSIGNED NULL,
 promotion_type VARCHAR(24) NOT NULL,
 day_key DATE NULL,
 sequence_day INT NULL,
 amount_minor BIGINT UNSIGNED NOT NULL,
 rollover_x DECIMAL(9,2) NOT NULL DEFAULT 0,
 wager_required_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 wager_progress_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 status ENUM('PENDING','LOCKED','COMPLETED') NOT NULL DEFAULT 'PENDING',
 financial_transaction_id CHAR(36) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_promotion_user_coupon (user_id,coupon_campaign_id),
 UNIQUE KEY uq_promotion_day (user_id,day_key),
 INDEX idx_promotion_user_status (user_id,status,created_at),
 CONSTRAINT fk_redemption_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_redemption_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_redemption_financial FOREIGN KEY (financial_transaction_id) REFERENCES financial_transactions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Histórico de apostas já alocadas a bônus: uma aposta nunca liquida dois requisitos.
CREATE TABLE IF NOT EXISTS promotion_wager_allocations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ledger_entry_id CHAR(36) NOT NULL,
 redemption_id CHAR(36) NOT NULL,
 amount_minor BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_wager_alloc_once (ledger_entry_id,redemption_id),
 INDEX idx_wager_alloc_ledger (ledger_entry_id),
 CONSTRAINT fk_wager_alloc_ledger FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE RESTRICT,
 CONSTRAINT fk_wager_alloc_redemption FOREIGN KEY (redemption_id) REFERENCES promotion_redemptions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promotion_coupon_attempts (
 user_id CHAR(36) PRIMARY KEY,
 window_started_at DATETIME(6) NOT NULL,
 attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 CONSTRAINT fk_coupon_attempt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
