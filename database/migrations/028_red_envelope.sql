-- V20: Envelope Vermelho diário com histórico separado e proteção contra duplicidade.
CREATE TABLE IF NOT EXISTS envelope_daily_claims (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id CHAR(36) NOT NULL,
 campaign_id BIGINT UNSIGNED NOT NULL,
 day_key DATE NOT NULL,
 base_amount_minor BIGINT UNSIGNED NOT NULL,
 multiplier DECIMAL(9,2) NOT NULL,
 final_amount_minor BIGINT UNSIGNED NOT NULL,
 redemption_id CHAR(36) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_envelope_daily (user_id,campaign_id,day_key),
 INDEX idx_envelope_campaign_day (campaign_id,day_key),
 CONSTRAINT fk_envelope_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_envelope_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_envelope_redemption FOREIGN KEY (redemption_id) REFERENCES promotion_redemptions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
