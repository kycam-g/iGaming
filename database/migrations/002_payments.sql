CREATE TABLE IF NOT EXISTS payment_gateways (
    code VARCHAR(64) PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    mode ENUM('SANDBOX','PRODUCTION') NOT NULL DEFAULT 'SANDBOX',
    sort_order INT NOT NULL DEFAULT 0,
    public_config JSON NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_transactions (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    gateway_code VARCHAR(64) NOT NULL,
    kind ENUM('DEPOSIT','WITHDRAWAL') NOT NULL,
    status ENUM('PENDING','PROCESSING','PAID','FAILED','CANCELLED','EXPIRED','REVIEW') NOT NULL DEFAULT 'PENDING',
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    external_id VARCHAR(190) NULL,
    idempotency_key VARCHAR(190) NOT NULL,
    payment_code TEXT NULL,
    payment_qr_code TEXT NULL,
    expires_at DATETIME(6) NULL,
    metadata JSON NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_payment_idempotency (user_id, kind, idempotency_key),
    UNIQUE KEY uq_payment_external (gateway_code, external_id),
    INDEX idx_payment_user_created (user_id, created_at),
    INDEX idx_payment_status (status, created_at),
    CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payment_gateway FOREIGN KEY (gateway_code) REFERENCES payment_gateways(code) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gateway_code VARCHAR(64) NOT NULL,
    event_key VARCHAR(190) NOT NULL,
    external_id VARCHAR(190) NULL,
    event_type VARCHAR(120) NULL,
    payload JSON NOT NULL,
    processed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_payment_webhook_event (gateway_code, event_key),
    INDEX idx_payment_webhook_external (gateway_code, external_id),
    CONSTRAINT fk_webhook_gateway FOREIGN KEY (gateway_code) REFERENCES payment_gateways(code) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO payment_gateways (code,name,enabled,mode,sort_order,public_config)
VALUES ('sandbox_pix','PIX Sandbox',1,'SANDBOX',1,JSON_OBJECT('min_amount_minor',100,'max_amount_minor',10000000))
ON DUPLICATE KEY UPDATE name=VALUES(name), public_config=VALUES(public_config);
