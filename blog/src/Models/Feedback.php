<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Модель обратной связи (Feedback)
 * Форма для всех пользователей, отправка email админу, Rate Limit (3 в час с IP)
 */
class Feedback
{
    private PDO $db;
    private const RATE_LIMIT_HOURS = 1;
    private const RATE_LIMIT_COUNT = 3;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Создание нового сообщения обратной связи
     * Проверка Rate Limit: макс 3 сообщения в час с одного IP
     * @return int|false ID сообщения или false (в т.ч. при превышении лимита)
     */
    public function create(array $data, string $ipAddress): int|false
    {
        // Проверка Rate Limit
        if ($this->isRateLimited($ipAddress)) {
            return false; // Превышен лимит
        }

        $stmt = $this->db->prepare("
            INSERT INTO feedback (user_id, name, email, subject, message, status)
            VALUES (:user_id, :name, :email, :subject, :message, 'pending')
        ");
        
        $result = $stmt->execute([
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message']
        ]);

        return $result ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Проверка Rate Limit для IP
     * Максимум 3 сообщения за последний час
     */
    private function isRateLimited(string $ipAddress): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM feedback 
            WHERE ip_address = :ip 
              AND created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)
        ");
        
        $stmt->bindValue(':ip', $ipAddress);
        $stmt->bindValue(':hours', self::RATE_LIMIT_HOURS, PDO::PARAM_INT);
        $stmt->execute();
        
        $count = (int)$stmt->fetchColumn();
        
        return $count >= self::RATE_LIMIT_COUNT;
    }

    /**
     * Получение сообщения по ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT f.*, u.login as user_login
            FROM feedback f
            LEFT JOIN users u ON f.user_id = u.id
            WHERE f.id = :id
        ");
        $stmt->execute(['id' => $id]);
        
        $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
        return $feedback ?: null;
    }

    /**
     * Обновление статуса и ответа админа
     */
    public function updateResponse(int $id, string $status, ?string $adminResponse = null): bool
    {
        if (!in_array($status, ['pending', 'processed', 'closed'])) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE feedback 
            SET status = :status, 
                admin_response = :admin_response,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'status' => $status,
            'admin_response' => $adminResponse,
            'id' => $id
        ]);
    }

    /**
     * Получение всех сообщений для админки
     */
    public function getAll(?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $where = $status ? "WHERE f.status = :status" : "";
        
        $stmt = $this->db->prepare("
            SELECT f.*, u.login as user_login
            FROM feedback f
            LEFT JOIN users u ON f.user_id = u.id
            $where
            ORDER BY f.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        
        if ($status) {
            $stmt->bindValue(':status', $status);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Подсчет сообщений по статусу
     */
    public function countByStatus(?string $status = null): int
    {
        if ($status === null) {
            $stmt = $this->db->query("SELECT COUNT(*) FROM feedback");
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM feedback WHERE status = :status");
            $stmt->execute(['status' => $status]);
        }
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Подсчет сообщений, ожидающих ответа
     */
    public function countPending(): int
    {
        return $this->countByStatus('pending');
    }

    /**
     * Получение количества сообщений от IP за последний час (для отладки)
     */
    public function getCountByIpLastHour(string $ipAddress): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM feedback 
            WHERE ip_address = :ip 
              AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute(['ip' => $ipAddress]);
        
        return (int)$stmt->fetchColumn();
    }
}
