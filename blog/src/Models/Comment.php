<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Модель комментария
 * Работа с таблицей comments, модерация, лимит времени на редактирование (15 мин)
 */
class Comment
{
    private PDO $db;
    private const EDIT_TIME_LIMIT = 900; // 15 минут в секундах

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Получение комментария по ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, u.login as author_login, u.role_id as author_role_id
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $comment ?: null;
    }

    /**
     * Создание нового комментария
     * Статус по умолчанию: pending (ожидает модерации)
     */
    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare("
            INSERT INTO comments (post_id, user_id, content, status)
            VALUES (:post_id, :user_id, :content, 'pending')
        ");
        
        $result = $stmt->execute([
            'post_id' => $data['post_id'],
            'user_id' => $data['user_id'],
            'content' => $data['content']
        ]);

        return $result ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Обновление комментария
     * Проверка на возможность редактирования (только свои + 15 минут)
     */
    public function update(int $id, int $userId, string $content): bool
    {
        // Проверяем, что комментарий принадлежит пользователю и не истекло время
        $stmt = $this->db->prepare("
            SELECT user_id, created_at 
            FROM comments 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            return false; // Комментарий не найден или не принадлежит пользователю
        }

        // Проверяем лимит времени (15 минут)
        $createdAt = strtotime($comment['created_at']);
        if ((time() - $createdAt) > self::EDIT_TIME_LIMIT) {
            return false; // Время на редактирование истекло
        }

        $stmt = $this->db->prepare("
            UPDATE comments 
            SET content = :content, updated_at = NOW(), status = 'pending'
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'content' => $content,
            'id' => $id
        ]);
    }

    /**
     * Удаление комментария
     * Только свои комментарии и в течение 15 минут
     */
    public function delete(int $id, int $userId): bool
    {
        // Проверяем принадлежность и время
        $stmt = $this->db->prepare("
            SELECT user_id, created_at 
            FROM comments 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            return false;
        }

        // Проверяем лимит времени (15 минут)
        $createdAt = strtotime($comment['created_at']);
        if ((time() - $createdAt) > self::EDIT_TIME_LIMIT) {
            return false; // Время на удаление истекло
        }

        $stmt = $this->db->prepare("DELETE FROM comments WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Одобрение/отклонение комментария (Moderator/Admin)
     */
    public function moderate(int $id, string $status): bool
    {
        if (!in_array($status, ['approved', 'rejected'])) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE comments 
            SET status = :status, updated_at = NOW()
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'status' => $status,
            'id' => $id
        ]);
    }

    /**
     * Получение комментариев к посту
     */
    public function getByPostId(int $postId, string $status = 'approved', int $limit = 100): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, u.login as author_login, u.role_id as author_role_id
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.post_id = :post_id AND c.status = :status
            ORDER BY c.created_at ASC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':post_id', $postId, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получение всех комментариев для модерации (Admin/Moderator)
     */
    public function getAllForModeration(string $status = 'pending', int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, u.login as author_login, p.title as post_title
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN posts p ON c.post_id = p.id
            WHERE c.status = :status
            ORDER BY c.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Подсчет комментариев к посту
     */
    public function countByPostId(int $postId, string $status = 'approved'): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM comments 
            WHERE post_id = :post_id AND status = :status
        ");
        $stmt->execute([
            'post_id' => $postId,
            'status' => $status
        ]);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Подсчет комментариев, ожидающих модерации
     */
    public function countPending(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM comments WHERE status = 'pending'");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Проверка возможности редактирования комментария
     */
    public function canEdit(int $commentId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT user_id, created_at 
            FROM comments 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute(['id' => $commentId, 'user_id' => $userId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            return false;
        }

        $createdAt = strtotime($comment['created_at']);
        return (time() - $createdAt) <= self::EDIT_TIME_LIMIT;
    }

    /**
     * Проверка возможности удаления комментария
     */
    public function canDelete(int $commentId, int $userId): bool
    {
        return $this->canEdit($commentId, $userId);
    }
}
