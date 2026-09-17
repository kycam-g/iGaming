CREATE TABLE IF NOT EXISTS site_visits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    path VARCHAR(190) NOT NULL,
    user_id CHAR(36) NULL,
    ip_hash CHAR(64) NULL,
    city VARCHAR(120) NULL,
    region VARCHAR(120) NULL,
    country VARCHAR(120) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX idx_site_visits_created (created_at),
    INDEX idx_site_visits_location (city, region, country),
    INDEX idx_site_visits_user (user_id, created_at),
    CONSTRAINT fk_site_visits_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
