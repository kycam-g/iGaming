-- V21: Roleta de Saque. O saldo da campanha é promocional e só vira carteira ao atingir a meta.
CREATE TABLE IF NOT EXISTS cashwheel_sessions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id CHAR(36) NOT NULL,
 campaign_id BIGINT UNSIGNED NOT NULL,
 started_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 expires_at DATETIME(6) NOT NULL,
 progress_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 claimed_at DATETIME(6) NULL,
 redemption_id CHAR(36) NULL,
 status ENUM('ACTIVE','CLAIMED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
 INDEX idx_cashwheel_session_user (user_id,campaign_id,status),
 CONSTRAINT fk_cashwheel_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_cashwheel_session_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_cashwheel_session_redemption FOREIGN KEY (redemption_id) REFERENCES promotion_redemptions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cashwheel_spins (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 session_id BIGINT UNSIGNED NOT NULL,
 user_id CHAR(36) NOT NULL,
 campaign_id BIGINT UNSIGNED NOT NULL,
 day_key DATE NOT NULL,
 prize_minor BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 INDEX idx_cashwheel_spin_day (user_id,campaign_id,day_key),
 CONSTRAINT fk_cashwheel_spin_session FOREIGN KEY (session_id) REFERENCES cashwheel_sessions(id) ON DELETE RESTRICT,
 CONSTRAINT fk_cashwheel_spin_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_cashwheel_spin_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cashwheel_referral_credits (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 session_id BIGINT UNSIGNED NOT NULL,
 user_id CHAR(36) NOT NULL,
 referred_user_id CHAR(36) NOT NULL,
 amount_minor BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_cashwheel_referral (session_id,referred_user_id),
 CONSTRAINT fk_cashwheel_referral_session FOREIGN KEY (session_id) REFERENCES cashwheel_sessions(id) ON DELETE RESTRICT,
 CONSTRAINT fk_cashwheel_referral_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_cashwheel_referred_user FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
