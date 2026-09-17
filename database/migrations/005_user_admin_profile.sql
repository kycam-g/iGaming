ALTER TABLE users
    ADD COLUMN public_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT AFTER id,
    ADD UNIQUE KEY uq_users_public_id (public_id);

CREATE TABLE IF NOT EXISTS user_payout_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    name VARCHAR(120) NOT NULL,
    type ENUM('PIX') NOT NULL DEFAULT 'PIX',
    key_type ENUM('CPF','CNPJ','EMAIL','PHONE','RANDOM') NOT NULL,
    key_value VARCHAR(255) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    INDEX idx_user_payout_accounts_user (user_id, created_at),
    CONSTRAINT fk_user_payout_accounts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
