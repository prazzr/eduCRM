<?php

declare(strict_types=1);

namespace EduCRM\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Ensure PHPMailer autoload is available
require_once __DIR__ . '/../../vendor/autoload.php';

class EmailNotificationService
{
    private \PDO $pdo;
    private string $fromEmail;
    private string $fromName;
    private bool $queueEnabled;

    // SMTP Configuration
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUsername;
    private string $smtpPassword;
    private string $smtpEncryption;

    public function __construct(\PDO $pdo)
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
        $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $this->fromEmail = $settings['smtp_from_email'] ?? 'noreply@educrm.local';
        $this->fromName = $settings['smtp_from_name'] ?? 'EduCRM';
        $this->queueEnabled = ($settings['email_queue_enabled'] ?? 'true') === 'true';

        // SMTP settings
        $this->smtpHost = $settings['smtp_host'] ?? '';
        $this->smtpPort = (int) ($settings['smtp_port'] ?? 587);
        $this->smtpUsername = $settings['smtp_username'] ?? '';
        $this->smtpPassword = $settings['smtp_password'] ?? '';
        $this->smtpEncryption = $settings['smtp_encryption'] ?? 'tls';
    }

    /**
     * Check if user has email notifications enabled for a specific type
     */
    public function isNotificationEnabled($userId, $notificationType)
    {
        $stmt = $this->pdo->prepare("SELECT email_enabled, $notificationType FROM notification_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch(\PDO::FETCH_ASSOC);

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
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$task || !$task['assigned_email']) {
            return false;
        }

        $defaultSubject = "New Task Assigned: " . $task['title'];
        $result = $this->renderTemplate('task_assignment', [
            'name' => $task['assigned_name'],
            'task_title' => $task['title'],
            'task_description' => $task['description'],
            'priority' => $task['priority'],
            'due_date' => $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : 'No due date',
            'task_url' => BASE_URL . 'modules/tasks/edit.php?id=' . $taskId
        ]);

        // Handle array return (subject + body) or string (body only - legacy backup)
        $body = is_array($result) ? $result['body'] : $result;
        $subject = (is_array($result) && !empty($result['subject'])) ? $result['subject'] : $defaultSubject;

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
        $appointment = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$appointment) {
            return false;
        }

        $sent = false;

        // Send to counselor
        if ($appointment['counselor_email'] && $this->isNotificationEnabled($appointment['counselor_id'], 'appointment_reminder')) {
            $clientName = $appointment['student_name'] ?? $appointment['inquiry_name'] ?? 'Client';

            $defaultSubject = "Appointment Reminder: " . $appointment['title'];
            $result = $this->renderTemplate('appointment_reminder', [
                'name' => $appointment['counselor_name'],
                'appointment_title' => $appointment['title'],
                'client_name' => $clientName,
                'appointment_date' => date('M d, Y \a\t h:i A', strtotime($appointment['appointment_date'])),
                'location' => $appointment['location'] ?? 'Not specified',
                'meeting_link_section' => $appointment['meeting_link'] ? "<p style='margin: 5px 0;'><strong>Meeting Link:</strong> <a href='" . $appointment['meeting_link'] . "'>" . $appointment['meeting_link'] . "</a></p>" : "",
                'meeting_link' => $appointment['meeting_link'],
                'appointment_url' => BASE_URL . 'modules/appointments/edit.php?id=' . $appointmentId
            ]);

            $body = is_array($result) ? $result['body'] : $result;
            $subject = (is_array($result) && !empty($result['subject'])) ? $result['subject'] : $defaultSubject;

            $sent = $this->queueEmail($appointment['counselor_email'], $appointment['counselor_name'], $subject, $body, 'appointment_reminder');
        }

        // Send to client if they have email
        $clientEmail = $appointment['student_email'] ?? $appointment['inquiry_email'];
        if ($clientEmail) {
            $clientName = $appointment['student_name'] ?? $appointment['inquiry_name'];

            $defaultSubject = "Appointment Reminder: " . $appointment['title'];
            $result = $this->renderTemplate('appointment_reminder_client', [
                'name' => $clientName,
                'appointment_title' => $appointment['title'],
                'counselor_name' => $appointment['counselor_name'],
                'appointment_date' => date('M d, Y \a\t h:i A', strtotime($appointment['appointment_date'])),
                'location' => $appointment['location'] ?? 'Not specified',
                'meeting_link_section' => $appointment['meeting_link'] ? "<p style='margin: 5px 0;'><strong>Meeting Link:</strong> <a href='" . $appointment['meeting_link'] . "'>" . $appointment['meeting_link'] . "</a></p>" : "",
                'meeting_link' => $appointment['meeting_link']
            ]);

            $body = is_array($result) ? $result['body'] : $result;
            $subject = (is_array($result) && !empty($result['subject'])) ? $result['subject'] : $defaultSubject;

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
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$task || !$task['assigned_email']) {
            return false;
        }

        if (!$this->isNotificationEnabled($task['assigned_to'], 'task_overdue')) {
            return false;
        }

        $daysOverdue = floor((time() - strtotime($task['due_date'])) / 86400);

        $defaultSubject = "Overdue Task Alert: " . $task['title'];
        $result = $this->renderTemplate('task_overdue', [
            'name' => $task['assigned_name'],
            'task_title' => $task['title'],
            'days_overdue' => $daysOverdue,
            'due_date' => date('M d, Y', strtotime($task['due_date'])),
            'priority' => $task['priority'],
            'task_url' => BASE_URL . 'modules/tasks/edit.php?id=' . $taskId
        ]);

        $body = is_array($result) ? $result['body'] : $result;
        $subject = (is_array($result) && !empty($result['subject'])) ? $result['subject'] : $defaultSubject;

        return $this->queueEmail($task['assigned_email'], $task['assigned_name'], $subject, $body, 'task_overdue');
    }

    /**
     * Send welcome email with credentials to new user/student
     */
    public function sendWelcomeEmail($userId, $plainPassword)
    {
        $stmt = $this->pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !$user['email']) {
            return false;
        }

        $defaultSubject = "Welcome to EduCRM - Your Account Has Been Created";
        $result = $this->renderTemplate('welcome', [
            'name' => $user['name'],
            'email' => $user['email'],
            'password' => $plainPassword,
            'login_url' => BASE_URL . 'login.php'
        ]);

        $body = is_array($result) ? $result['body'] : $result;
        $subject = (is_array($result) && !empty($result['subject'])) ? $result['subject'] : $defaultSubject;

        return $this->queueEmail($user['email'], $user['name'], $subject, $body, 'welcome');
    }

    /**
     * Send profile update notification
     */
    public function sendProfileUpdateNotification($userId, $changesArray)
    {
        $stmt = $this->pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !$user['email']) {
            return false;
        }

        if (!$this->isNotificationEnabled($userId, 'profile_update')) {
            return false;
        }

        // Format changes as HTML list
        $changesHtml = '<ul style="margin: 0; padding-left: 20px;">';
        foreach ($changesArray as $field => $change) {
            $changesHtml .= '<li style="margin: 5px 0;"><strong>' . htmlspecialchars($field) . ':</strong> '
                . htmlspecialchars($change['old'] ?? 'N/A') . ' → ' . htmlspecialchars($change['new'] ?? 'N/A') . '</li>';
        }
        $changesHtml .= '</ul>';

        $defaultSubject = "Your Profile Has Been Updated";
        $result = $this->renderTemplate('profile_update', [
            'name' => $user['name'],
            'changes' => $changesHtml,
            'profile_url' => BASE_URL . 'modules/users/profile.php'
        ]);

        $body = is_array($result) ? $result['body'] : $result;
        $subject = (is_array($result) && !empty($result['subject'])) ? $result['subject'] : $defaultSubject;

        return $this->queueEmail($user['email'], $user['name'], $subject, $body, 'profile_update');
    }

    /**
     * Send visa workflow status update notification
     */
    public function sendWorkflowUpdateNotification($workflowId, $oldStage, $newStage)
    {
        $stmt = $this->pdo->prepare("
            SELECT vw.*, u.name, u.email, c.name as country_name
            FROM visa_workflows vw 
            JOIN users u ON vw.student_id = u.id 
            LEFT JOIN countries c ON vw.country_id = c.id
            WHERE vw.id = ?
        ");
        $stmt->execute([$workflowId]);
        $workflow = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$workflow || !$workflow['email']) {
            return false;
        }

        if (!$this->isNotificationEnabled($workflow['student_id'], 'workflow_update')) {
            return false;
        }

        // Get stage names from visa_stages table
        $stageStmt = $this->pdo->prepare("SELECT id, name FROM visa_stages WHERE id IN (?, ?)");
        $stageStmt->execute([$oldStage, $newStage]);
        $stages = [];
        while ($row = $stageStmt->fetch(\PDO::FETCH_ASSOC)) {
            $stages[$row['id']] = $row['name'];
        }

        $defaultSubject = "Visa Application Update - " . ($stages[$newStage] ?? 'Stage Changed');
        $result = $this->renderTemplate('workflow_update', [
            'name' => $workflow['name'],
            'application_title' => $workflow['country_name'] ? 'Visa for ' . $workflow['country_name'] : 'Your Visa Application',
            'old_stage' => $stages[$oldStage] ?? 'Previous Stage',
            'new_stage' => $stages[$newStage] ?? 'New Stage',
            'updated_at' => date('M d, Y h:i A'),
            'workflow_url' => BASE_URL . 'modules/visa/update.php?student_id=' . $workflow['student_id']
        ]);

        $body = is_array($result) ? $result['body'] : $result;
        $subject = (is_array($result) && !empty($result['subject'])) ? $result['subject'] : $defaultSubject;

        return $this->queueEmail($workflow['email'], $workflow['name'], $subject, $body, 'workflow_update');
    }

    /**
     * Send document status change notification
     */
    public function sendDocumentStatusNotification($documentId, $newStatus, $remarks = null)
    {
        $stmt = $this->pdo->prepare("
            SELECT sd.*, u.name, u.email, dt.name as document_type_name
            FROM student_documents sd 
            JOIN users u ON sd.student_id = u.id 
            LEFT JOIN document_types dt ON sd.document_type_id = dt.id
            WHERE sd.id = ?
        ");
        $stmt->execute([$documentId]);
        $document = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$document || !$document['email']) {
            return false;
        }

        if (!$this->isNotificationEnabled($document['student_id'], 'document_update')) {
            return false;
        }

        // Status color mapping
        $statusColors = [
            'pending' => '#f59e0b',
            'uploaded' => '#3b82f6',
            'verified' => '#10b981',
            'rejected' => '#ef4444',
            'not_required' => '#6b7280'
        ];

        $remarksHtml = $remarks ? '<p style="margin: 10px 0 0 0;"><strong>Remarks:</strong> ' . htmlspecialchars($remarks) . '</p>' : '';

        $docName = $document['document_type_name'] ?? $document['original_filename'] ?? $document['title'] ?? 'Document';
        $defaultSubject = "Document Status Update: " . $docName;
        $result = $this->renderTemplate('document_update', [
            'name' => $document['name'],
            'document_name' => $docName,
            'status' => ucfirst(str_replace('_', ' ', $newStatus)),
            'status_color' => $statusColors[$newStatus] ?? '#64748b',
            'remarks' => $remarksHtml,
            'documents_url' => BASE_URL . 'modules/visa/update.php?student_id=' . $document['student_id']
        ]);

        $body = is_array($result) ? $result['body'] : $result;
        $subject = (is_array($result) && !empty($result['subject'])) ? $result['subject'] : $defaultSubject;

        return $this->queueEmail($document['email'], $document['name'], $subject, $body, 'document_update');
    }

    /**
     * Send course enrollment notification
     */
    public function sendEnrollmentNotification($enrollmentId)
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, 
                   cl.name as class_name, cl.start_date, cl.schedule_info as schedule, cl.course_id,
                   co.name as course_name,
                   u.name as student_name, u.email as student_email,
                   t.name as instructor_name
            FROM enrollments e
            JOIN classes cl ON e.class_id = cl.id
            LEFT JOIN courses co ON cl.course_id = co.id
            JOIN users u ON e.student_id = u.id
            LEFT JOIN users t ON cl.teacher_id = t.id
            WHERE e.id = ?
        ");
        $stmt->execute([$enrollmentId]);
        $enrollment = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$enrollment || !$enrollment['student_email']) {
            return false;
        }

        if (!$this->isNotificationEnabled($enrollment['student_id'], 'enrollment')) {
            return false;
        }

        $displayName = $enrollment['class_name'] ?? $enrollment['course_name'] ?? 'Class';
        $defaultSubject = "Class Enrollment Confirmation: " . $displayName;
        $result = $this->renderTemplate('enrollment', [
            'name' => $enrollment['student_name'],
            'course_name' => $displayName,
            'start_date' => $enrollment['start_date'] ? date('M d, Y', strtotime($enrollment['start_date'])) : 'TBD',
            'instructor' => $enrollment['instructor_name'] ?? 'TBD',
            'schedule' => $enrollment['schedule'] ?? 'TBD',
            'course_url' => BASE_URL . 'modules/lms/classroom.php?id=' . $enrollment['class_id']
        ]);

        $body = is_array($result) ? $result['body'] : $result;
        $subject = (is_array($result) && !empty($result['subject'])) ? $result['subject'] : $defaultSubject;

        return $this->queueEmail($enrollment['student_email'], $enrollment['student_name'], $subject, $body, 'enrollment');
    }

    /**
     * Render email template
     * Returns array ['subject' => string, 'body' => string]
     */
    private function renderTemplate($templateKey, $data)
    {
        $subject = null;
        $body = '';

        // Try to load from database first
        try {
            $stmt = $this->pdo->prepare("SELECT subject, body_html FROM email_templates WHERE template_key = ? AND is_active = 1");
            $stmt->execute([$templateKey]);
            $dbTemplate = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($dbTemplate && !empty($dbTemplate['body_html'])) {
                $body = $dbTemplate['body_html'];
                $subject = $dbTemplate['subject'];
            }
        } catch (\PDOException $e) {
            // Table might not exist, fall through to hardcoded templates
        }

        // Fallback to hardcoded templates if DB template missing
        if (empty($body)) {
            $templates = $this->getHardcodedTemplates();
            $body = $templates[$templateKey] ?? '';
        }

        // Perform variable substitution
        foreach ($data as $key => $value) {
            $placeholder = '{' . $key . '}';
            $body = str_replace($placeholder, $value, $body);
            if ($subject) {
                $subject = str_replace($placeholder, $value, $subject);
            }
        }

        // Inline CSS (Simple regex-based approach to avoid dependencies)
        $body = $this->inlineCss($body);

        // Return array if subject exists (new format), or just string (legacy back-compat)
        // ideally we always return array, but keeping string return for body-only calls might be safer
        // wait, let's standardize on returning array internally, but methods calling this need to handle it.
        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Simple CSS Inliner
     * Moves style block content to inline styles
     */
    private function inlineCss($html)
    {
        // Extract style blocks
        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $matches);
        $css = implode("\n", $matches[1]);

        // If no CSS, return original
        if (empty($css)) {
            return $html;
        }

        // Remove style blocks from HTML
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Parse simple CSS rules (selector { property: value; })
        preg_match_all('/([^{]+)\s*\{\s*([^}]+)\s*\}/', $css, $rules, PREG_SET_ORDER);

        foreach ($rules as $rule) {
            $selector = trim($rule[1]);
            $style = trim($rule[2]);

            // Skip complex selectors for this simple implementation
            if (strpos($selector, ':') !== false || strpos($selector, '>') !== false) {
                continue;
            }

            // Convert CSS classes to inline styles using DOMDocument if available, or simple str_replace
            // Since we can't rely on DOMDocument being perfect with HTML5 snippets, let's try a safer regex approach for class names
            if (strpos($selector, '.') === 0) {
                $className = substr($selector, 1);
                $html = preg_replace(
                    '/class=["\']([^"\']*)\b' . preg_quote($className, '/') . '\b([^"\']*)["\']/',
                    'class="$1' . $className . '$2" style="' . $style . '"',
                    $html
                );
            }
            // Tag selectors (e.g., p, h1)
            elseif (ctype_alpha($selector)) {
                $html = preg_replace(
                    '/<' . $selector . '\b([^>]*)>/',
                    '<' . $selector . ' style="' . $style . '" $1>',
                    $html
                );
            }
        }

        return $html;
    }

    private function getHardcodedTemplates()
    {
        return [
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
                        {meeting_link_section}
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
                         {meeting_link_section}
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
            ",
            'welcome' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #0f766e;'>🎉 Welcome to EduCRM!</h2>
                    <p>Hi {name},</p>
                    <p>Your account has been successfully created. Below are your login credentials:</p>
                    <div style='background: #f0fdfa; padding: 20px; border-left: 4px solid #0f766e; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><strong>Email:</strong> {email}</p>
                        <p style='margin: 5px 0;'><strong>Temporary Password:</strong> <code style='background: #e2e8f0; padding: 2px 8px; border-radius: 4px;'>{password}</code></p>
                    </div>
                    <p style='color: #dc2626; font-weight: bold;'>⚠️ For security, please change your password after your first login.</p>
                    <p><a href='{login_url}' style='background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Login to Your Account</a></p>
                    <p style='color: #64748b; font-size: 12px; margin-top: 30px;'>If you did not request this account, please ignore this email.</p>
                </div>
            ",
            'profile_update' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #0f766e;'>📝 Profile Updated</h2>
                    <p>Hi {name},</p>
                    <p>Your profile has been updated with the following changes:</p>
                    <div style='background: #f8fafc; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;'>
                        {changes}
                    </div>
                    <p>If you did not make these changes, please contact support immediately.</p>
                    <p><a href='{profile_url}' style='background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Your Profile</a></p>
                    <p style='color: #64748b; font-size: 12px; margin-top: 30px;'>This is an automated notification from EduCRM.</p>
                </div>
            ",
            'workflow_update' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #0f766e;'>🔄 Visa Workflow Update</h2>
                    <p>Hi {name},</p>
                    <p>Your visa application status has been updated:</p>
                    <div style='background: #f0fdfa; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><strong>Application:</strong> {application_title}</p>
                        <p style='margin: 5px 0;'><strong>Previous Stage:</strong> <span style='color: #64748b;'>{old_stage}</span></p>
                        <p style='margin: 5px 0;'><strong>New Stage:</strong> <span style='color: #0f766e; font-weight: bold;'>{new_stage}</span></p>
                        <p style='margin: 5px 0;'><strong>Updated:</strong> {updated_at}</p>
                    </div>
                    <p><a href='{workflow_url}' style='background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Application</a></p>
                    <p style='color: #64748b; font-size: 12px; margin-top: 30px;'>This is an automated notification from EduCRM.</p>
                </div>
            ",
            'document_update' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #0f766e;'>📄 Document Status Update</h2>
                    <p>Hi {name},</p>
                    <p>A document associated with your profile has been updated:</p>
                    <div style='background: #f8fafc; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><strong>Document:</strong> {document_name}</p>
                        <p style='margin: 5px 0;'><strong>Status:</strong> <span style='color: {status_color}; font-weight: bold;'>{status}</span></p>
                        {remarks}
                    </div>
                    <p><a href='{documents_url}' style='background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Documents</a></p>
                    <p style='color: #64748b; font-size: 12px; margin-top: 30px;'>This is an automated notification from EduCRM.</p>
                </div>
            ",
            'enrollment' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #0f766e;'>📚 Course Enrollment Confirmation</h2>
                    <p>Hi {name},</p>
                    <p>Congratulations! You have been enrolled in a new course:</p>
                    <div style='background: #f0fdfa; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;'>
                        <h3 style='margin: 0 0 10px 0; color: #0f766e;'>{course_name}</h3>
                        <p style='margin: 5px 0;'><strong>Start Date:</strong> {start_date}</p>
                        <p style='margin: 5px 0;'><strong>Instructor:</strong> {instructor}</p>
                        <p style='margin: 5px 0;'><strong>Schedule:</strong> {schedule}</p>
                    </div>
                    <p><a href='{course_url}' style='background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Course Details</a></p>
                    <p style='color: #64748b; font-size: 12px; margin-top: 30px;'>Welcome aboard! This is an automated notification from EduCRM.</p>
                </div>
            "
        ];
    }


    /**
     * Process email queue (send pending emails)
     */
    public function processQueue($limit = 50)
    {
        $limit = (int) $limit;
        $stmt = $this->pdo->prepare("
            SELECT * FROM email_queue 
            WHERE status = 'pending' 
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            AND attempts < 3
            ORDER BY created_at ASC
            LIMIT $limit
        ");
        $stmt->execute();
        $emails = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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
     * Actually send the email using PHPMailer with SMTP
     */
    private function sendEmail($emailData)
    {
        $mail = new PHPMailer(true);

        try {
            // Check if SMTP is configured
            if (!empty($this->smtpHost)) {
                // SMTP Configuration
                $mail->isSMTP();
                $mail->Host = $this->smtpHost;
                $mail->Port = $this->smtpPort;

                // Authentication
                if (!empty($this->smtpUsername)) {
                    $mail->SMTPAuth = true;
                    $mail->Username = $this->smtpUsername;
                    $mail->Password = $this->smtpPassword;
                }

                // Encryption
                if ($this->smtpEncryption === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($this->smtpEncryption === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                }

                // For development/testing - disable certificate verification if needed
                // Remove these lines in production
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
            } else {
                // Fallback to PHP mail() if SMTP not configured
                $mail->isMail();
            }

            // Sender
            $mail->setFrom($this->fromEmail, $this->fromName);

            // Recipient
            $mail->addAddress($emailData['recipient_email'], $emailData['recipient_name'] ?? '');

            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $emailData['subject'];
            $mail->Body = $emailData['body'];
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $emailData['body']));

            $mail->send();
            return true;

        } catch (\Exception $e) {
            // Log the error
            error_log("Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Send a test email to verify SMTP configuration
     */
    public function sendTestEmail(string $toEmail): array
    {
        $mail = new PHPMailer(true);

        try {
            // Check if SMTP is configured
            if (empty($this->smtpHost)) {
                return ['success' => false, 'error' => 'SMTP host not configured'];
            }

            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->Port = $this->smtpPort;

            // Authentication
            if (!empty($this->smtpUsername)) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->smtpUsername;
                $mail->Password = $this->smtpPassword;
            }

            // Encryption
            if ($this->smtpEncryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->smtpEncryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            // For development - disable certificate verification
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Sender
            $mail->setFrom($this->fromEmail, $this->fromName);

            // Recipient
            $mail->addAddress($toEmail);

            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'EduCRM Test Email';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #0f766e;'>✅ SMTP Configuration Test</h2>
                    <p>Congratulations! Your SMTP settings are working correctly.</p>
                    <div style='background: #f0fdf4; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><strong>SMTP Host:</strong> {$this->smtpHost}</p>
                        <p style='margin: 5px 0;'><strong>SMTP Port:</strong> {$this->smtpPort}</p>
                        <p style='margin: 5px 0;'><strong>Encryption:</strong> " . strtoupper($this->smtpEncryption) . "</p>
                        <p style='margin: 5px 0;'><strong>From:</strong> {$this->fromName} &lt;{$this->fromEmail}&gt;</p>
                    </div>
                    <p style='color: #64748b; font-size: 12px; margin-top: 30px;'>
                        Sent at " . date('Y-m-d H:i:s') . " from EduCRM
                    </p>
                </div>
            ";
            $mail->AltBody = "SMTP Test Successful! Your email configuration is working.";

            $mail->send();
            return ['success' => true, 'message' => 'Test email sent successfully'];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }

    /**
     * Check if SMTP is properly configured
     */
    public function isSmtpConfigured(): bool
    {
        return !empty($this->smtpHost) && !empty($this->smtpUsername);
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
