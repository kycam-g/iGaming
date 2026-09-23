-- V22: Sorteio de Cartas / coleção HAPPY.
CREATE TABLE IF NOT EXISTS lottery_states (
 user_id CHAR(36) NOT NULL,
 campaign_id BIGINT UNSIGNED NOT NULL,
 collection_json JSON NOT NULL,
 completed_collections INT UNSIGNED NOT NULL DEFAULT 0,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY (user_id,campaign_id),
 CONSTRAINT fk_lottery_state_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_lottery_state_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lottery_spins (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id CHAR(36) NOT NULL,
 campaign_id BIGINT UNSIGNED NOT NULL,
 day_key DATE NOT NULL,
 result_letter CHAR(1) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 INDEX idx_lottery_spin_day (user_id,campaign_id,day_key),
 CONSTRAINT fk_lottery_spin_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_lottery_spin_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lottery_claims (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id CHAR(36) NOT NULL,
 campaign_id BIGINT UNSIGNED NOT NULL,
 collection_number INT UNSIGNED NOT NULL,
 redemption_id CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_lottery_collection_claim (user_id,campaign_id,collection_number),
 CONSTRAINT fk_lottery_claim_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_lottery_claim_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_lottery_claim_redemption FOREIGN KEY (redemption_id) REFERENCES promotion_redemptions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
