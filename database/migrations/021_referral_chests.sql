-- V14: indicação vinculada ao cadastro. Nenhum indicado é atribuído retroativamente.
CREATE TABLE IF NOT EXISTS referral_codes (
 user_id CHAR(36) NOT NULL PRIMARY KEY,
 code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_referral_code (code),
 CONSTRAINT fk_referral_code_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS player_referrals (
 referred_user_id CHAR(36) NOT NULL PRIMARY KEY,
 referrer_user_id CHAR(36) NOT NULL,
 code_used VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 INDEX idx_player_referrals_referrer (referrer_user_id,created_at),
 CONSTRAINT fk_player_referral_child FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_player_referral_parent FOREIGN KEY (referrer_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
