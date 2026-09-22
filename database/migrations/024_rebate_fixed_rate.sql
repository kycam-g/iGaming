-- V16.1: rebate fixo. Eventos históricos preservados. Reativação explícita obrigatória.
SET @rebate_has_rate := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rebate_settings' AND COLUMN_NAME='rate_basis_points');
SET @rebate_rate_sql := IF(@rebate_has_rate=0,'ALTER TABLE rebate_settings ADD COLUMN rate_basis_points SMALLINT UNSIGNED NOT NULL DEFAULT 0','DO 0');
PREPARE rebate_rate_stmt FROM @rebate_rate_sql;
EXECUTE rebate_rate_stmt;
DEALLOCATE PREPARE rebate_rate_stmt;
CREATE TABLE IF NOT EXISTS rebate_rate_periods (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 started_at DATETIME(6) NOT NULL,
 rate_basis_points SMALLINT UNSIGNED NOT NULL,
 INDEX idx_rebate_period_start(started_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Impede que antigas faixas afetem novas apostas durante a conversão.
UPDATE rebate_settings SET enabled=0,activated_at=NULL,rate_basis_points=0 WHERE id=1;
