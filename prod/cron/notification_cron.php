<?php
/**
 * Email Notification Cron Job
 * 
 * This script should be run periodically (e.g., every 15 minutes) via cron:
 * Schedule: 0,15,30,45 * * * * /usr/bin/php /path/to/educrm/cron/notification_cron.php
 * 
 * Or on Windows Task Scheduler:
 * C:\xampp\php\php.exe C:\xampp\htdocs\CRM\cron\notification_cron.php
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

$emailService = new \EduCRM\Services\EmailNotificationService($pdo);
$appointmentService = new \EduCRM\Services\AppointmentService($pdo);
$taskService = new \EduCRM\Services\TaskService($pdo);

echo "[" . date('Y-m-d H:i:s') . "] Starting notification cron job...\n";

// =========================================================================
// 1. SEND APPOINTMENT REMINDERS (24 hours before)
// =========================================================================

echo "\n1. Checking for appointment reminders...\n";

$tomorrow = date('Y-m-d H:i:s', strtotime('+24 hours'));
$tomorrowEnd = date('Y-m-d H:i:s', strtotime('+25 hours'));

$stmt = $pdo->prepare("
    SELECT id FROM appointments 
    WHERE status = 'scheduled' 
    AND reminder_sent = FALSE
    AND appointment_date BETWEEN ? AND ?
");
$stmt->execute([$tomorrow, $tomorrowEnd]);
$appointments = $stmt->fetchAll(PDO::FETCH_COLUMN);

$remindersSent = 0;
foreach ($appointments as $appointmentId) {
    if ($emailService->sendAppointmentReminder($appointmentId)) {
        $remindersSent++;
        echo "   ✓ Sent reminder for appointment #$appointmentId\n";
    }
}

echo "   Total reminders sent: $remindersSent\n";

// =========================================================================
// 2. SEND OVERDUE TASK ALERTS
// =========================================================================

echo "\n2. Checking for overdue tasks...\n";

$stmt = $pdo->query("
    SELECT id FROM tasks 
    WHERE status IN ('pending', 'in_progress')
    AND due_date < NOW()
    AND due_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
");
$overdueTasks = $stmt->fetchAll(PDO::FETCH_COLUMN);

$alertsSent = 0;
foreach ($overdueTasks as $taskId) {
    if ($emailService->sendOverdueTaskAlert($taskId)) {
        $alertsSent++;
        echo "   ✓ Sent overdue alert for task #$taskId\n";
    }
}

echo "   Total overdue alerts sent: $alertsSent\n";

// =========================================================================
// 3. PROCESS EMAIL QUEUE
// =========================================================================

echo "\n3. Processing email queue...\n";

$result = $emailService->processQueue(50);
echo "   Emails sent: {$result['sent']}\n";
echo "   Emails failed: {$result['failed']}\n";

// =========================================================================
// SUMMARY
// =========================================================================

echo "\n" . str_repeat("=", 60) . "\n";
echo "Notification cron job completed!\n";
echo "- Appointment reminders: $remindersSent\n";
echo "- Overdue task alerts: $alertsSent\n";
echo "- Queue processed: {$result['sent']} sent, {$result['failed']} failed\n";
echo str_repeat("=", 60) . "\n";
echo "[" . date('Y-m-d H:i:s') . "] Finished\n\n";
