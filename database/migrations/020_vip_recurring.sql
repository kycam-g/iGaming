-- V13.1: períodos VIP auditáveis. Configuração inicial desabilita bônus recorrentes
-- até o administrador definir valores, modo e confirmar a ativação.
CREATE TABLE IF NOT EXISTS vip_program_settings (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 maintenance_mode ENUM('lifelong','downgrade') NOT NULL DEFAULT 'lifelong',
 downgrade_steps SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 activated_at DATETIME(6) NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO vip_program_settings(id,enabled,maintenance_mode,downgrade_steps) VALUES (1,0,'lifelong',1);
CREATE TABLE IF NOT EXISTS vip_memberships (
 user_id CHAR(36) NOT NULL PRIMARY KEY,
 earned_level INT NOT NULL DEFAULT 0,
 effective_level INT NOT NULL DEFAULT 0,
 suspended TINYINT(1) NOT NULL DEFAULT 0,
 joined_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_vip_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS vip_monthly_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id CHAR(36) NOT NULL,
 period_key CHAR(7) NOT NULL,
 volume_minor BIGINT UNSIGNED NOT NULL,
 required_minor BIGINT UNSIGNED NOT NULL,
 before_level INT NOT NULL,
 after_level INT NOT NULL,
 rule_applied VARCHAR(24) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_vip_month_review (user_id,period_key),
 CONSTRAINT fk_vip_review_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS vip_period_awards (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id CHAR(36) NOT NULL,
 campaign_id BIGINT UNSIGNED NOT NULL,
 kind ENUM('daily','weekly','monthly') NOT NULL,
 period_key VARCHAR(16) NOT NULL,
 level_snapshot INT NOT NULL,
 amount_minor BIGINT UNSIGNED NOT NULL,
 rollover_x DECIMAL(9,2) NOT NULL,
 state ENUM('AVAILABLE','CLAIMED') NOT NULL DEFAULT 'AVAILABLE',
 redemption_id CHAR(36) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 claimed_at DATETIME(6) NULL,
 UNIQUE KEY uq_vip_period (user_id,kind,period_key),
 INDEX idx_vip_period_state (state,created_at),
 CONSTRAINT fk_vip_period_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_vip_period_campaign FOREIGN KEY (campaign_id) REFERENCES promotion_configurations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_vip_period_redemption FOREIGN KEY (redemption_id) REFERENCES promotion_redemptions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
