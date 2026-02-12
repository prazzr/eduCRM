<?php

declare(strict_types=1);

namespace EduCRM\Services;

class NotificationService
{
    private \PDO $pdo;
    private int $userId;

    public function __construct(\PDO $pdo, int $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }

    public function getUnread(): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll();
    }

    public function markAllRead()
    {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        return $stmt->execute([$this->userId]);
    }

    public static function add($pdo, $userId, $message)
    {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        return $stmt->execute([$userId, $message]);
    }
}
