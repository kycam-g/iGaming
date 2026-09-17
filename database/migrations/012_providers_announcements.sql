-- Mantém provedores e jogos existentes. A logo é opcional e nunca substitui o nome.
ALTER TABLE casino_providers ADD COLUMN IF NOT EXISTS logo_path VARCHAR(255) NULL DEFAULT NULL AFTER name;
CREATE TABLE IF NOT EXISTS platform_announcements (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 message VARCHAR(240) NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 100,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 INDEX idx_announcements_public (enabled,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
