ALTER TABLE platform_notifications
  ADD COLUMN priority VARCHAR(12) NOT NULL DEFAULT 'normal' AFTER category;

CREATE INDEX idx_platform_notifications_priority ON platform_notifications(priority,enabled,created_at);
