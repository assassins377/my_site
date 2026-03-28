<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Модель жалоб (Reports)
 * Пользователи создают жалобы, админ/модератор управляет статусами
 * Типы: comment, post, user
 */
class Report
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Создание жалобы
     * Уникальность: одна жалоба от пользователя на объект
     * @param string $type Тип объекта: 'comment', 'post', 'user'
     * @return int|false ID жалобы или false
     */
    public function create(int $objectId, string $type, int $userId, string $reason): int|false
    {
        if (!in_array($type, ['comment', 'post', 'user'])) {
            return false;
        }

        // Проверяем, нет ли уже жалобы от этого пользователя на этот объект
        if ($this->exists($objectId, $type, $userId)) {
            return false; // Уже есть жалоба
        }

        $stmt = $this->db->prepare("
            INSERT INTO reports (reportable_type, reportable_id, user_id, reason, status)
            VALUES (:type, :object_id, :user_id, :reason, 'pending')
        ");
        
        $result = $stmt->execute([
            'type' => $type,
            'object_id' => $objectId,
            'user_id' => $userId,
            'reason' => $reason
        ]);

        return $result ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Проверка существования жалобы от пользователя на объект
     */
    private function exists(int $objectId, string $type, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT id FROM reports 
            WHERE reportable_type = :type 
              AND reportable_id = :object_id 
              AND user_id = :user_id
        ");
        
        $stmt->execute([
            'type' => $type,
            'object_id' => $objectId,
            'user_id' => $userId
        ]);
        
        return $stmt->fetch() !== false;
    }

    /**
     * Получение жалобы по ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, u.login as reporter_login, 
                   CASE 
                       WHEN r.reportable_type = 'comment' THEN c.content
                       WHEN r.reportable_type = 'post' THEN p.title
                       WHEN r.reportable_type = 'user' THEN ur.login
                   END as object_info
            FROM reports r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN comments c ON r.reportable_type = 'comment' AND r.reportable_id = c.id
            LEFT JOIN posts p ON r.reportable_type = 'post' AND r.reportable_id = p.id
            LEFT JOIN users ur ON r.reportable_type = 'user' AND r.reportable_id = ur.id
            WHERE r.id = :id
        ");
        $stmt->execute(['id' => $id]);
        
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        return $report ?: null;
    }

    /**
     * Обновление статуса жалобы (Admin/Moderator)
     * Статусы: pending, approved, rejected
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['pending', 'approved', 'rejected'])) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE reports 
            SET status = :status, updated_at = NOW()
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'status' => $status,
            'id' => $id
        ]);
    }

    /**
     * Получение всех жалоб для модерации
     */
    public function getAllForModeration(?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $where = $status ? "WHERE r.status = :status" : "";
        
        $stmt = $this->db->prepare("
            SELECT r.*, u.login as reporter_login,
                   CASE 
                       WHEN r.reportable_type = 'comment' THEN c.content
                       WHEN r.reportable_type = 'post' THEN p.title
                       WHEN r.reportable_type = 'user' THEN ur.login
                   END as object_info,
                   CASE 
                       WHEN r.reportable_type = 'comment' THEN c.id
                       WHEN r.reportable_type = 'post' THEN p.id
                       WHEN r.reportable_type = 'user' THEN ur.id
                   END as object_id
            FROM reports r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN comments c ON r.reportable_type = 'comment' AND r.reportable_id = c.id
            LEFT JOIN posts p ON r.reportable_type = 'post' AND r.reportable_id = p.id
            LEFT JOIN users ur ON r.reportable_type = 'user' AND r.reportable_id = ur.id
            $where
            ORDER BY r.created_at DESC
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
     * Подсчет жалоб по статусу
     */
    public function countByStatus(?string $status = null): int
    {
        if ($status === null) {
            $stmt = $this->db->query("SELECT COUNT(*) FROM reports");
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM reports WHERE status = :status");
            $stmt->execute(['status' => $status]);
        }
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Подсчет жалоб, ожидающих рассмотрения
     */
    public function countPending(): int
    {
        return $this->countByStatus('pending');
    }
}
