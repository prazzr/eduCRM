<?php
namespace EduCRM\Services;

use PDO;

class TemplateService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all templates (Events)
     */
    public function getAllEvents()
    {
        $stmt = $this->pdo->query("
            SELECT ne.*, 
                   ct.is_email_enabled, ct.is_sms_enabled, ct.is_whatsapp_enabled
            FROM notification_events ne
            LEFT JOIN centralized_templates ct ON ne.event_key = ct.event_key
            ORDER BY ne.category, ne.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get Template Content by Event Key
     */
    public function getTemplate($eventKey)
    {
        $stmt = $this->pdo->prepare("
            SELECT ct.*, ne.name as event_name, ne.description, ne.category
            FROM notification_events ne
            LEFT JOIN centralized_templates ct ON ne.event_key = ct.event_key
            WHERE ne.event_key = ?
        ");
        $stmt->execute([$eventKey]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Save Template Content
     */
    public function saveTemplate($eventKey, $data)
    {
        // Check if exists
        $stmt = $this->pdo->prepare("SELECT id FROM centralized_templates WHERE event_key = ?");
        $stmt->execute([$eventKey]);
        $exists = $stmt->fetch();

        if ($exists) {
            $sql = "UPDATE centralized_templates SET 
                    email_subject = ?, email_body = ?, is_email_enabled = ?,
                    sms_body = ?, is_sms_enabled = ?,
                    whatsapp_body = ?, is_whatsapp_enabled = ?,
                    updated_by = ?
                    WHERE event_key = ?";
            $params = [
                $data['email_subject'] ?? null,
                $data['email_body'] ?? null,
                isset($data['is_email_enabled']) ? 1 : 0,
                $data['sms_body'] ?? null,
                isset($data['is_sms_enabled']) ? 1 : 0,
                $data['whatsapp_body'] ?? null,
                isset($data['is_whatsapp_enabled']) ? 1 : 0,
                $_SESSION['user_id'] ?? null,
                $eventKey
            ];
        } else {
            $sql = "INSERT INTO centralized_templates 
                    (email_subject, email_body, is_email_enabled, 
                     sms_body, is_sms_enabled, 
                     whatsapp_body, is_whatsapp_enabled, 
                     updated_by, event_key)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $data['email_subject'] ?? null,
                $data['email_body'] ?? null,
                isset($data['is_email_enabled']) ? 1 : 0,
                $data['sms_body'] ?? null,
                isset($data['is_sms_enabled']) ? 1 : 0,
                $data['whatsapp_body'] ?? null,
                isset($data['is_whatsapp_enabled']) ? 1 : 0,
                $_SESSION['user_id'] ?? null,
                $eventKey
            ];
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
}
