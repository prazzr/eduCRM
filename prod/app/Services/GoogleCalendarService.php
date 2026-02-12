<?php
namespace EduCRM\Services;

/**
 * Google Calendar Integration Service
 * Handles OAuth flow and appointment sync to Google Calendar
 */
class GoogleCalendarService
{
    private $pdo;
    private $config;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->config = require __DIR__ . '/../../config/google.php';
    }

    /**
     * Check if Google Calendar integration is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['client_id']) && !empty($this->config['client_secret']);
    }

    /**
     * Get OAuth authorization URL
     */
    public function getAuthUrl(int $userId): string
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Google Calendar is not configured. Please set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in your .env file.');
        }

        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $this->config['scopes']),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => base64_encode(json_encode(['user_id' => $userId, 'csrf' => bin2hex(random_bytes(16))]))
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Handle OAuth callback and store tokens
     */
    public function handleCallback(string $code, int $userId): bool
    {
        $tokenData = $this->exchangeCodeForToken($code);

        if (!$tokenData || isset($tokenData['error'])) {
            throw new \Exception('Failed to get access token: ' . ($tokenData['error_description'] ?? 'Unknown error'));
        }

        return $this->storeTokens($userId, $tokenData);
    }

    /**
     * Exchange authorization code for access token
     */
    private function exchangeCodeForToken(string $code): ?array
    {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'code' => $code,
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'redirect_uri' => $this->config['redirect_uri'],
                'grant_type' => 'authorization_code'
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }

        return json_decode($response, true);
    }

    /**
     * Store OAuth tokens in database
     */
    private function storeTokens(int $userId, array $tokenData): bool
    {
        $expiresAt = date('Y-m-d H:i:s', time() + ($tokenData['expires_in'] ?? 3600));

        $stmt = $this->pdo->prepare("
            INSERT INTO user_calendar_tokens 
            (user_id, provider, access_token, refresh_token, token_expires_at, is_active)
            VALUES (?, 'google', ?, ?, ?, TRUE)
            ON DUPLICATE KEY UPDATE 
                access_token = VALUES(access_token),
                refresh_token = COALESCE(VALUES(refresh_token), refresh_token),
                token_expires_at = VALUES(token_expires_at),
                is_active = TRUE,
                updated_at = NOW()
        ");

        return $stmt->execute([
            $userId,
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? null,
            $expiresAt
        ]);
    }

    /**
     * Get valid access token for user, refreshing if needed
     */
    private function getValidToken(int $userId): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT access_token, refresh_token, token_expires_at 
            FROM user_calendar_tokens 
            WHERE user_id = ? AND is_active = TRUE
        ");
        $stmt->execute([$userId]);
        $tokenData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tokenData) {
            return null;
        }

        // Check if token is expired or about to expire (5 min buffer)
        if (strtotime($tokenData['token_expires_at']) < (time() + 300)) {
            if (!$tokenData['refresh_token']) {
                return null;
            }
            return $this->refreshToken($userId, $tokenData['refresh_token']);
        }

        return $tokenData['access_token'];
    }

    /**
     * Refresh expired access token
     */
    private function refreshToken(int $userId, string $refreshToken): ?string
    {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'refresh_token' => $refreshToken,
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'grant_type' => 'refresh_token'
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($response, true);

        if (!$tokenData || isset($tokenData['error'])) {
            // Token refresh failed, mark as inactive
            $this->disconnect($userId);
            return null;
        }

        // Update stored token
        $expiresAt = date('Y-m-d H:i:s', time() + ($tokenData['expires_in'] ?? 3600));
        $stmt = $this->pdo->prepare("
            UPDATE user_calendar_tokens 
            SET access_token = ?, token_expires_at = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$tokenData['access_token'], $expiresAt, $userId]);

        return $tokenData['access_token'];
    }

    /**
     * Check if user has connected calendar
     */
    public function isConnected(int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM user_calendar_tokens 
            WHERE user_id = ? AND is_active = TRUE
        ");
        $stmt->execute([$userId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Disconnect calendar
     */
    public function disconnect(int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE user_calendar_tokens 
            SET is_active = FALSE, updated_at = NOW() 
            WHERE user_id = ?
        ");
        return $stmt->execute([$userId]);
    }

    /**
     * Sync appointment to Google Calendar
     */
    public function syncAppointment(array $appointment): array
    {
        $userId = $appointment['counselor_id'] ?? $appointment['user_id'] ?? null;

        if (!$userId) {
            return ['success' => false, 'error' => 'No user ID for sync'];
        }

        $accessToken = $this->getValidToken($userId);
        if (!$accessToken) {
            return ['success' => false, 'error' => 'Calendar not connected or token expired'];
        }

        // Build event data
        $eventData = $this->buildEventData($appointment);

        // Check if already synced
        $existingSync = $this->getSyncRecord($appointment['id'], $userId);

        try {
            if ($existingSync && $existingSync['provider_event_id']) {
                // Update existing event
                $result = $this->updateCalendarEvent($accessToken, $existingSync['provider_event_id'], $eventData);
            } else {
                // Create new event
                $result = $this->createCalendarEvent($accessToken, $eventData);
            }

            if ($result['success']) {
                $this->recordSync($appointment['id'], $userId, $result['event_id'], 'synced');
                return ['success' => true, 'event_id' => $result['event_id']];
            } else {
                $this->recordSync($appointment['id'], $userId, null, 'failed', $result['error']);
                return ['success' => false, 'error' => $result['error']];
            }
        } catch (\Exception $e) {
            $this->recordSync($appointment['id'], $userId, null, 'failed', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build Google Calendar event data from appointment
     */
    private function buildEventData(array $appointment): array
    {
        $startTime = $appointment['appointment_date'] ?? $appointment['start_time'];
        $endTime = $appointment['end_time'] ?? date('Y-m-d H:i:s', strtotime($startTime) + 3600);
        $timezone = $this->config['timezone'];

        $description = $this->buildDescription($appointment);

        $event = [
            'summary' => $appointment['title'] ?? 'Appointment',
            'description' => $description,
            'start' => [
                'dateTime' => date('c', strtotime($startTime)),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => date('c', strtotime($endTime)),
                'timeZone' => $timezone,
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 1440], // 24 hours
                    ['method' => 'popup', 'minutes' => 60],   // 1 hour
                ],
            ],
        ];

        // Add location if available
        if (!empty($appointment['meeting_link'])) {
            $event['location'] = $appointment['meeting_link'];
        } elseif (!empty($appointment['location'])) {
            $event['location'] = $appointment['location'];
        }

        return $event;
    }

    /**
     * Build event description from appointment data
     */
    private function buildDescription(array $appointment): string
    {
        $desc = [];

        if (!empty($appointment['client_name'])) {
            $desc[] = "Client: {$appointment['client_name']}";
        }
        if (!empty($appointment['client_email'])) {
            $desc[] = "Email: {$appointment['client_email']}";
        }
        if (!empty($appointment['client_phone'])) {
            $desc[] = "Phone: {$appointment['client_phone']}";
        }
        if (!empty($appointment['notes'])) {
            $desc[] = "\nNotes: {$appointment['notes']}";
        }

        $desc[] = "\n---\nManaged by EduCRM";

        return implode("\n", $desc);
    }

    /**
     * Create event in Google Calendar
     */
    private function createCalendarEvent(string $accessToken, array $eventData): array
    {
        $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($eventData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($result['id'])) {
            return ['success' => true, 'event_id' => $result['id']];
        }

        return ['success' => false, 'error' => $result['error']['message'] ?? 'Failed to create event'];
    }

    /**
     * Update existing event in Google Calendar
     */
    private function updateCalendarEvent(string $accessToken, string $eventId, array $eventData): array
    {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . urlencode($eventId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($eventData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($result['id'])) {
            return ['success' => true, 'event_id' => $result['id']];
        }

        return ['success' => false, 'error' => $result['error']['message'] ?? 'Failed to update event'];
    }

    /**
     * Delete event from Google Calendar
     */
    public function deleteEvent(int $appointmentId, int $userId): bool
    {
        $accessToken = $this->getValidToken($userId);
        if (!$accessToken) {
            return false;
        }

        $syncRecord = $this->getSyncRecord($appointmentId, $userId);
        if (!$syncRecord || !$syncRecord['provider_event_id']) {
            return false;
        }

        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . urlencode($syncRecord['provider_event_id']);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            // Remove sync record
            $stmt = $this->pdo->prepare("DELETE FROM calendar_sync_events WHERE appointment_id = ? AND user_id = ?");
            $stmt->execute([$appointmentId, $userId]);
            return true;
        }

        return false;
    }

    /**
     * Get sync record for appointment
     */
    private function getSyncRecord(int $appointmentId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM calendar_sync_events 
            WHERE appointment_id = ? AND user_id = ?
        ");
        $stmt->execute([$appointmentId, $userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Record sync status
     */
    private function recordSync(int $appointmentId, int $userId, ?string $eventId, string $status, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO calendar_sync_events (appointment_id, user_id, provider_event_id, last_synced_at, sync_status, error_message)
            VALUES (?, ?, ?, NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE 
                provider_event_id = COALESCE(VALUES(provider_event_id), provider_event_id),
                last_synced_at = NOW(),
                sync_status = VALUES(sync_status),
                error_message = VALUES(error_message)
        ");
        $stmt->execute([$appointmentId, $userId, $eventId, $status, $error]);
    }
}
