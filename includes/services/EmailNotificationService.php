<?php

class EmailNotificationService
{
    private $pdo;
    private $fromEmail;
    private $fromName;
    private $queueEnabled;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    /**
     * Load email settings from system_settings
     */
    private function loadSettings()
    {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%' OR setting_key = 'email_queue_enabled'");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->fromEmail = $settings['smtp_from_email'] ?? 'noreply@educrm.local';
        $this->fromName = $settings['smtp_from_name'] ?? 'EduCRM';
        $this->queueEnabled = ($settings['email_queue_enabled'] ?? 'true') === 'true';
    }

    /**
     * Check if user has email notifications enabled for a specific type
     */
    public function isNotificationEnabled($userId, $notificationType)
    {
        $stmt = $this->pdo->prepare("SELECT email_enabled, $notificationType FROM notification_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prefs) {
            return true; // Default to enabled if no preferences set
        }

        return $prefs['email_enabled'] && $prefs[$notificationType];
    }

    /**
     * Queue an email for sending
     */
    public function queueEmail($recipientEmail, $recipientName, $subject, $body, $template = null, $scheduledAt = null)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO email_queue (recipient_email, recipient_name, subject, body, template, scheduled_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $recipientEmail,
            $recipientName,
            $subject,
            $body,
            $template,
            $scheduledAt
        ]);
    }

    /**
     * Send task assignment notification
     */
    public function sendTaskAssignmentNotification($taskId, $assignedUserId)
    {
        if (!$this->isNotificationEnabled($assignedUserId, 'task_assignment')) {
            return false;
        }

        // Get task and user details
        $stmt = $this->pdo->prepare("
            SELECT t.*, u.name as assigned_name, u.email as assigned_email
            FROM tasks t
            JOIN users u ON t.assigned_to = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task || !$task['assigned_email']) {
            return false;
        }

        $subject = "New Task Assigned: " . $task['title'];
        $body = $this->renderTemplate('task_assignment', [
            'name' => $task['assigned_name'],
            'task_title' => $task['title'],
            'task_description' => $task['description'],
            'priority' => $task['priority'],
            'due_date' => $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : 'No due date',
            'task_url' => BASE_URL . 'modules/tasks/edit.php?id=' . $taskId
        ]);

        return $this->queueEmail($task['assigned_email'], $task['assigned_name'], $subject, $body, 'task_assignment');
    }

    /**
     * Send appointment reminder notification
     */
    public function sendAppointmentReminder($appointmentId)
    {
        // Get appointment and counselor details
        $stmt = $this->pdo->prepare("
            SELECT a.*, 
                   u1.name as student_name, u1.email as student_email,
                   i.name as inquiry_name, i.email as inquiry_email,
                   u2.name as counselor_name, u2.email as counselor_email
            FROM appointments a
            LEFT JOIN users u1 ON a.student_id = u1.id
            LEFT JOIN inquiries i ON a.inquiry_id = i.id
            JOIN users u2 ON a.counselor_id = u2.id
            WHERE a.id = ? AND a.status = 'scheduled'
        ");
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment) {
            return false;
        }

        $sent = false;

        // Send to counselor
        if ($appointment['counselor_email'] && $this->isNotificationEnabled($appointment['counselor_id'], 'appointment_reminder')) {
            $clientName = $appointment['student_name'] ?? $appointment['inquiry_name'] ?? 'Client';

            $subject = "Appointment Reminder: " . $appointment['title'];
            $body = $this->renderTemplate('appointment_reminder', [
                'name' => $appointment['counselor_name'],
                'appointment_title' => $appointment['title'],
                'client_name' => $clientName,
                'appointment_date' => date('M d, Y \a\t h:i A', strtotime($appointment['appointment_date'])),
                'location' => $appointment['location'] ?? 'Not specified',
                'meeting_link' => $appointment['meeting_link'],
                'appointment_url' => BASE_URL . 'modules/appointments/edit.php?id=' . $appointmentId
            ]);

            $sent = $this->queueEmail($appointment['counselor_email'], $appointment['counselor_name'], $subject, $body, 'appointment_reminder');
        }

        // Send to client if they have email
        $clientEmail = $appointment['student_email'] ?? $appointment['inquiry_email'];
        if ($clientEmail) {
            $clientName = $appointment['student_name'] ?? $appointment['inquiry_name'];

            $subject = "Appointment Reminder: " . $appointment['title'];
            $body = $this->renderTemplate('appointment_reminder_client', [
                'name' => $clientName,
                'appointment_title' => $appointment['title'],
                'counselor_name' => $appointment['counselor_name'],
                'appointment_date' => date('M d, Y \a\t h:i A', strtotime($appointment['appointment_date'])),
                'location' => $appointment['location'] ?? 'Not specified',
                'meeting_link' => $appointment['meeting_link']
            ]);

            $this->queueEmail($clientEmail, $clientName, $subject, $body, 'appointment_reminder_client');
        }

        // Mark reminder as sent
        if ($sent) {
            $this->pdo->prepare("UPDATE appointments SET reminder_sent = TRUE WHERE id = ?")->execute([$appointmentId]);
        }

        return $sent;
    }

    /**
     * Send overdue task alert
     */
    public function sendOverdueTaskAlert($taskId)
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, u.name as assigned_name, u.email as assigned_email
            FROM tasks t
            JOIN users u ON t.assigned_to = u.id
            WHERE t.id = ? AND t.status != 'completed'
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task || !$task['assigned_email']) {
            return false;
        }

        if (!$this->isNotificationEnabled($task['assigned_to'], 'task_overdue')) {
            return false;
        }

        $daysOverdue = floor((time() - strtotime($task['due_date'])) / 86400);

        $subject = "Overdue Task Alert: " . $task['title'];
        $body = $this->renderTemplate('task_overdue', [
            'name' => $task['assigned_name'],
            'task_title' => $task['title'],
            'days_overdue' => $daysOverdue,
            'due_date' => date('M d, Y', strtotime($task['due_date'])),
            'priority' => $task['priority'],
            'task_url' => BASE_URL . 'modules/tasks/edit.php?id=' . $taskId
        ]);

        return $this->queueEmail($task['assigned_email'], $task['assigned_name'], $subject, $body, 'task_overdue');
    }

    /**
     * Render email template
     */
    private function renderTemplate($template, $data)
    {
        $templates = [
            'task_assignment' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #4f46e5;'>New Task Assigned</h2>
                    <p>Hi {name},</p>
                    <p>A new task has been assigned to you:</p>
                    <div style='background: #f8fafc; padding: 15px; border-left: 4px solid #4f46e5; margin: 20px 0;'>
                        <h3 style='margin: 0 0 10px 0;'>{task_title}</h3>
                        <p style='margin: 5px 0;'><strong>Description:</strong> {task_description}</p>
                        <p style='margin: 5px 0;'><strong>Priority:</strong> <span style='text-transform: uppercase;'>{priority}</span></p>
                        <p style='margin: 5px 0;'><strong>Due Date:</strong> {due_date}</p>
                    </div>
                    <p><a href='{task_url}' style='background: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Task</a></p>
                    <p style='color: #64748b; font-size: 12px; margin-top: 30px;'>This is an automated notification from EduCRM.</p>
                </div>
            ",
            'appointment_reminder' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #4f46e5;'>Appointment Reminder</h2>
                    <p>Hi {name},</p>
                    <p>This is a reminder for your upcoming appointment:</p>
                    <div style='background: #f8fafc; padding: 15px; border-left: 4px solid #3b82f6; margin: 20px 0;'>
                        <h3 style='margin: 0 0 10px 0;'>{appointment_title}</h3>
                        <p style='margin: 5px 0;'><strong>Client:</strong> {client_name}</p>
                        <p style='margin: 5px 0;'><strong>Date & Time:</strong> {appointment_date}</p>
                        <p style='margin: 5px 0;'><strong>Location:</strong> {location}</p>
                        " . ($data['meeting_link'] ?? false ? "<p style='margin: 5px 0;'><strong>Meeting Link:</strong> <a href='{meeting_link}'>{meeting_link}</a></p>" : "") . "
                    </div>
                    <p><a href='{appointment_url}' style='background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Appointment</a></p>
                    <p style='color: #64748b; font-size: 12px; margin-top: 30px;'>This is an automated reminder from EduCRM.</p>
                </div>
            ",
            'appointment_reminder_client' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #4f46e5;'>Appointment Reminder</h2>
                    <p>Hi {name},</p>
                    <p>This is a reminder for your upcoming appointment with {counselor_name}:</p>
                    <div style='background: #f8fafc; padding: 15px; border-left: 4px solid #3b82f6; margin: 20px 0;'>
                        <h3 style='margin: 0 0 10px 0;'>{appointment_title}</h3>
                        <p style='margin: 5px 0;'><strong>Date & Time:</strong> {appointment_date}</p>
                        <p style='margin: 5px 0;'><strong>Location:</strong> {location}</p>
                        " . ($data['meeting_link'] ?? false ? "<p style='margin: 5px 0;'><strong>Meeting Link:</strong> <a href='{meeting_link}'>{meeting_link}</a></p>" : "") . "
                    </div>
                    <p style='color: #64748b; font-size: 12px; margin-top: 30px;'>This is an automated reminder from EduCRM.</p>
                </div>
            ",
            'task_overdue' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #ef4444;'>⚠️ Overdue Task Alert</h2>
                    <p>Hi {name},</p>
                    <p>The following task is now <strong>{days_overdue} day(s) overdue</strong>:</p>
                    <div style='background: #fef2f2; padding: 15px; border-left: 4px solid #ef4444; margin: 20px 0;'>
                        <h3 style='margin: 0 0 10px 0;'>{task_title}</h3>
                        <p style='margin: 5px 0;'><strong>Priority:</strong> <span style='text-transform: uppercase;'>{priority}</span></p>
                        <p style='margin: 5px 0;'><strong>Was Due:</strong> {due_date}</p>
                    </div>
                    <p><a href='{task_url}' style='background: #ef4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Complete Task Now</a></p>
                    <p style='color: #64748b; font-size: 12px; margin-top: 30px;'>This is an automated alert from EduCRM.</p>
                </div>
            "
        ];

        $template = $templates[$template] ?? '';

        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        return $template;
    }

    /**
     * Process email queue (send pending emails)
     */
    public function processQueue($limit = 50)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM email_queue 
            WHERE status = 'pending' 
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            AND attempts < 3
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sent = 0;
        $failed = 0;

        foreach ($emails as $email) {
            if ($this->sendEmail($email)) {
                $sent++;
                $this->updateEmailStatus($email['id'], 'sent');
            } else {
                $failed++;
                $this->updateEmailStatus($email['id'], 'failed', 'Failed to send email');
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Actually send the email (using PHP mail or SMTP)
     */
    private function sendEmail($emailData)
    {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";

        // Use PHP's mail() function (for development)
        // In production, you would use PHPMailer or similar for SMTP
        return mail(
            $emailData['recipient_email'],
            $emailData['subject'],
            $emailData['body'],
            $headers
        );
    }

    /**
     * Update email queue status
     */
    private function updateEmailStatus($emailId, $status, $errorMessage = null)
    {
        if ($status === 'sent') {
            $stmt = $this->pdo->prepare("UPDATE email_queue SET status = ?, sent_at = NOW(), attempts = attempts + 1 WHERE id = ?");
            $stmt->execute([$status, $emailId]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE email_queue SET status = ?, error_message = ?, attempts = attempts + 1 WHERE id = ?");
            $stmt->execute([$status, $errorMessage, $emailId]);
        }
    }
}
