CREATE TABLE IF NOT EXISTS users (
    id CHAR(36) PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    username VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('ACTIVE','BLOCKED','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_credentials (
    user_id CHAR(36) PRIMARY KEY,
    password_hash VARCHAR(255) NOT NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_user_credentials_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    last_used_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX idx_user_sessions_user_id (user_id),
    INDEX idx_user_sessions_lookup (token_hash, revoked_at, expires_at),
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallets (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL UNIQUE,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_accounts (
    id CHAR(36) PRIMARY KEY,
    wallet_id CHAR(36) NOT NULL,
    type ENUM('CASH','BONUS','AFFILIATE') NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    balance_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_wallet_account (wallet_id, type, currency),
    CONSTRAINT fk_wallet_accounts_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_transactions (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    type VARCHAR(40) NOT NULL,
    status ENUM('PENDING','COMPLETED','FAILED','CANCELLED') NOT NULL,
    reference_type VARCHAR(80) NOT NULL,
    reference_id VARCHAR(190) NOT NULL,
    correlation_id CHAR(36) NOT NULL,
    metadata JSON NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    INDEX idx_financial_transactions_user (user_id, created_at),
    INDEX idx_financial_transactions_reference (reference_type, reference_id),
    INDEX idx_financial_transactions_correlation (correlation_id),
    CONSTRAINT fk_financial_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ledger_entries (
    id CHAR(36) PRIMARY KEY,
    transaction_id CHAR(36) NOT NULL,
    account_id CHAR(36) NOT NULL,
    direction ENUM('CREDIT','DEBIT') NOT NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    balance_before_minor BIGINT UNSIGNED NOT NULL,
    balance_after_minor BIGINT UNSIGNED NOT NULL,
    reference_type VARCHAR(80) NOT NULL,
    reference_id VARCHAR(190) NOT NULL,
    correlation_id CHAR(36) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX idx_ledger_account (account_id, created_at),
    INDEX idx_ledger_transaction (transaction_id),
    INDEX idx_ledger_reference (reference_type, reference_id),
    CONSTRAINT fk_ledger_transaction FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_account FOREIGN KEY (account_id) REFERENCES wallet_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS idempotency_keys (
    `key` VARCHAR(190) NOT NULL,
    scope VARCHAR(80) NOT NULL,
    transaction_id CHAR(36) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`key`, scope),
    INDEX idx_idempotency_transaction (transaction_id),
    CONSTRAINT fk_idempotency_transaction FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_type VARCHAR(40) NOT NULL,
    actor_id VARCHAR(190) NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(120) NULL,
    entity_id VARCHAR(190) NULL,
    ip VARCHAR(45) NULL,
    metadata JSON NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX idx_audit_entity (entity_type, entity_id, created_at),
    INDEX idx_audit_actor (actor_type, actor_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS ledger_no_update;
CREATE TRIGGER ledger_no_update BEFORE UPDATE ON ledger_entries
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ledger_entries is immutable';

DROP TRIGGER IF EXISTS ledger_no_delete;
CREATE TRIGGER ledger_no_delete BEFORE DELETE ON ledger_entries
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ledger_entries is immutable';
