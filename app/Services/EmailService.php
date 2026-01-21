<?php

declare(strict_types=1);

namespace EduCRM\Services;

class EmailService
{
    private string $logFile;

    public function __construct()
    {
        // Log file location
        $this->logFile = __DIR__ . '/../../logs/email.log';
    }

    public function sendWelcomeEmail($user, $plainPassword)
    {
        $subject = "Welcome to EduCRM";
        $body = "Dear " . htmlspecialchars($user['name']) . ",\n\n";
        $body .= "Your account has been created successfully.\n";
        $body .= "Here are your login credentials:\n\n";
        $body .= "Email: " . $user['email'] . "\n";
        $body .= "Password: " . $plainPassword . "\n\n";
        $body .= "Please login at: " . BASE_URL . "login.php\n";
        $body .= "Thank you,\nEduCRM Team";

        return $this->logEmail($user['email'], $subject, $body);
    }

    public function sendInquiryReceipt($email, $name)
    {
        $subject = "Inquiry Received";
        $body = "Dear " . htmlspecialchars($name) . ",\n\n";
        $body .= "Thank you for contacting us. We have received your inquiry and a counselor will get in touch with you shortly.\n\n";
        $body .= "Best Regards,\nEduCRM Team";

        return $this->logEmail($email, $subject, $body);
    }

    private function logEmail($to, $subject, $body)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] TO: $to | SUBJECT: $subject\n";
        $logEntry .= "BODY:\n$body\n";
        $logEntry .= "---------------------------------------------------\n";

        // Append to log file
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        return true;
    }
}
