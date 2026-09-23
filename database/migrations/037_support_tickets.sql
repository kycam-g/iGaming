CREATE TABLE IF NOT EXISTS support_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_code VARCHAR(32) NOT NULL UNIQUE,
    user_id CHAR(36) NOT NULL,
    category VARCHAR(24) NOT NULL,
    priority VARCHAR(16) NOT NULL DEFAULT 'normal',
    subject VARCHAR(160) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'OPEN',
    assigned_admin_id CHAR(36) NULL,
    last_user_message_at DATETIME(6) NULL,
    last_admin_message_at DATETIME(6) NULL,
    resolved_at DATETIME(6) NULL,
    closed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    INDEX idx_support_tickets_user (user_id,updated_at),
    INDEX idx_support_tickets_queue (status,priority,updated_at),
    CONSTRAINT fk_support_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_ticket_admin FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    sender_type VARCHAR(16) NOT NULL,
    sender_id VARCHAR(36) NULL,
    message VARCHAR(3000) NOT NULL,
    attachment_path VARCHAR(255) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX idx_support_messages_ticket (ticket_id,id),
    CONSTRAINT fk_support_message_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
