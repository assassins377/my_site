<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Модель закладок (Bookmarks)
 * AJAX toggle, страница в профиле пользователя
 */
class Bookmark
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Добавление/удаление закладки (toggle)
     * @return array ['action' => 'added'|'removed', 'success' => bool]
     */
    public function toggle(int $postId, int $userId): array
    {
        // Проверяем, есть ли уже закладка
        $existingId = $this->getBookmarkId($postId, $userId);

        if ($existingId) {
            // Удаляем закладку
            $stmt = $this->db->prepare("DELETE FROM bookmarks WHERE id = :id");
            $success = $stmt->execute(['id' => $existingId]);
            
            return [
                'action' => 'removed',
                'success' => $success
            ];
        } else {
            // Добавляем закладку
            $stmt = $this->db->prepare("
                INSERT INTO bookmarks (user_id, post_id)
                VALUES (:user_id, :post_id)
            ");
            
            $success = $stmt->execute([
                'user_id' => $userId,
                'post_id' => $postId
            ]);

            return [
                'action' => 'added',
                'success' => $success
            ];
        }
    }

    /**
     * Получение ID закладки
     */
    private function getBookmarkId(int $postId, int $userId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id FROM bookmarks 
            WHERE user_id = :user_id AND post_id = :post_id
        ");
        $stmt->execute([
            'user_id' => $userId,
            'post_id' => $postId
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['id'] : null;
    }

    /**
     * Проверка, есть ли закладка у пользователя
     */
    public function isBookmarked(int $postId, int $userId): bool
    {
        return $this->getBookmarkId($postId, $userId) !== null;
    }

    /**
     * Получение всех закладок пользователя с информацией о постах
     */
    public function getByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT b.*, p.title, p.title_en, p.slug, p.poster_image, 
                   c.name as category_name
            FROM bookmarks b
            LEFT JOIN posts p ON b.post_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE b.user_id = :user_id
            ORDER BY b.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Подсчет количества закладок пользователя
     */
    public function countByUser(int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM bookmarks 
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Удаление закладки по ID
     */
    public function delete(int $bookmarkId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM bookmarks 
            WHERE id = :id AND user_id = :user_id
        ");
        
        return $stmt->execute([
            'id' => $bookmarkId,
            'user_id' => $userId
        ]);
    }
}
