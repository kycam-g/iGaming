-- V17: fundos diários, com apuração por apostas e ganhos PlayFiver confirmados.
-- A campanha nasce desativada. Somente dias completos após a ativação são elegíveis.
CREATE TABLE IF NOT EXISTS rescue_settings (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 effective_day DATE NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO rescue_settings(id,enabled) VALUES(1,0);
CREATE TABLE IF NOT EXISTS rescue_daily_awards (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id CHAR(36) NOT NULL,
 period_key DATE NOT NULL,
 campaign_id BIGINT UNSIGNED NULL,
 bet_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 win_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 loss_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 amount_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 refund_basis_points SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 rollover_x DECIMAL(9,2) NOT NULL DEFAULT 0,
 state ENUM('INELIGIBLE','AVAILABLE','CLAIMED','EXPIRED') NOT NULL DEFAULT 'INELIGIBLE',
 redemption_id CHAR(36) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 claimed_at DATETIME(6) NULL,
 UNIQUE KEY uq_rescue_user_day(user_id,period_key),
 INDEX idx_rescue_period(period_key,state),
 CONSTRAINT fk_rescue_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_rescue_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_rescue_redemption FOREIGN KEY (redemption_id) REFERENCES promotion_redemptions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
