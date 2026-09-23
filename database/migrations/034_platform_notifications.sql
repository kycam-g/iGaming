CREATE TABLE IF NOT EXISTS platform_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(24) NOT NULL,
    audience VARCHAR(16) NOT NULL DEFAULT 'ALL',
    user_id CHAR(36) NULL,
    title VARCHAR(120) NOT NULL,
    message VARCHAR(1000) NOT NULL,
    link_path VARCHAR(255) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    starts_at DATETIME(6) NULL,
    ends_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    INDEX idx_platform_notifications_public (enabled,starts_at,ends_at,created_at),
    INDEX idx_platform_notifications_user (user_id,enabled,created_at),
    CONSTRAINT fk_platform_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_notification_reads (
    user_id CHAR(36) NOT NULL,
    notification_id BIGINT UNSIGNED NOT NULL,
    read_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (user_id,notification_id),
    INDEX idx_user_notification_reads_notification (notification_id,read_at),
    CONSTRAINT fk_user_notification_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_notification_reads_notification FOREIGN KEY (notification_id) REFERENCES platform_notifications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
