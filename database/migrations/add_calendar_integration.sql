-- Google Calendar Integration Migration
-- Run this to add calendar sync support

-- Store user calendar OAuth tokens
CREATE TABLE IF NOT EXISTS user_calendar_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    provider ENUM('google', 'outlook') DEFAULT 'google',
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    calendar_id VARCHAR(255) DEFAULT 'primary',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Track synced calendar events
CREATE TABLE IF NOT EXISTS calendar_sync_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    user_id INT NOT NULL,
    provider_event_id VARCHAR(255),
    last_synced_at DATETIME,
    sync_status ENUM('synced', 'pending', 'failed') DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sync (appointment_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index for faster lookups
CREATE INDEX idx_calendar_sync_status ON calendar_sync_events(sync_status);
CREATE INDEX idx_calendar_sync_appointment ON calendar_sync_events(appointment_id);
