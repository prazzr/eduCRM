-- User Notification Preferences
-- Stores user preferences for notification channels

CREATE TABLE IF NOT EXISTS user_notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    channel_type VARCHAR(50) NOT NULL,  -- 'email', 'sms', 'whatsapp', 'viber', 'push'
    is_enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_channel (user_id, channel_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create index for faster lookups
CREATE INDEX idx_notif_prefs_user ON user_notification_preferences(user_id);
CREATE INDEX idx_notif_prefs_channel ON user_notification_preferences(channel_type);
