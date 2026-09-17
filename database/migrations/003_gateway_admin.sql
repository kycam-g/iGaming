ALTER TABLE payment_gateways
    ADD COLUMN deposit_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER enabled,
    ADD COLUMN withdrawal_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER deposit_enabled,
    ADD COLUMN priority_deposit INT NOT NULL DEFAULT 100 AFTER mode,
    ADD COLUMN priority_withdrawal INT NOT NULL DEFAULT 100 AFTER priority_deposit,
    ADD COLUMN min_deposit_minor BIGINT UNSIGNED NOT NULL DEFAULT 100 AFTER priority_withdrawal,
    ADD COLUMN max_deposit_minor BIGINT UNSIGNED NULL AFTER min_deposit_minor,
    ADD COLUMN min_withdrawal_minor BIGINT UNSIGNED NOT NULL DEFAULT 100 AFTER max_deposit_minor,
    ADD COLUMN max_withdrawal_minor BIGINT UNSIGNED NULL AFTER min_withdrawal_minor,
    ADD COLUMN credentials_encrypted LONGTEXT NULL AFTER max_withdrawal_minor,
    ADD COLUMN settings JSON NULL AFTER credentials_encrypted;

UPDATE payment_gateways
SET priority_deposit = CASE WHEN sort_order <= 0 THEN 100 ELSE sort_order END,
    settings = COALESCE(settings, JSON_OBJECT()),
    deposit_enabled = CASE WHEN enabled=1 THEN 1 ELSE deposit_enabled END
WHERE code IS NOT NULL;

INSERT INTO payment_gateways (code,name,enabled,deposit_enabled,withdrawal_enabled,mode,sort_order,priority_deposit,priority_withdrawal,min_deposit_minor,max_deposit_minor,min_withdrawal_minor,max_withdrawal_minor,credentials_encrypted,settings,public_config)
VALUES ('pixup','Pixup',0,1,0,'PRODUCTION',10,10,10,100,NULL,100,NULL,NULL,JSON_OBJECT('base_url','https://api.pixupbr.com','verify_webhook_signature',false),JSON_OBJECT('method','PIX'))
ON DUPLICATE KEY UPDATE name=VALUES(name), settings=COALESCE(payment_gateways.settings,VALUES(settings));

CREATE TABLE IF NOT EXISTS admin_users (
    id CHAR(36) PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('ACTIVE','BLOCKED') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_sessions (
    id CHAR(36) PRIMARY KEY,
    admin_user_id CHAR(36) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX idx_admin_session_lookup (token_hash,revoked_at,expires_at),
    CONSTRAINT fk_admin_sessions_user FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
