<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Модель рейтинга (1-10)
 * Один голос от пользователя на пост, AJAX обновление
 */
class Rating
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Постановка/обновление оценки пользователем
     * @return bool|int ID записи или true при обновлении
     */
    public function setRating(int $postId, int $userId, int $rating): bool|int
    {
        if ($rating < 1 || $rating > 10) {
            return false;
        }

        // Проверяем, есть ли уже оценка от этого пользователя
        $existingId = $this->getUserRatingId($postId, $userId);

        if ($existingId) {
            // Обновляем существующую оценку
            $stmt = $this->db->prepare("
                UPDATE ratings 
                SET rating = :rating, created_at = NOW()
                WHERE id = :id
            ");
            return $stmt->execute([
                'rating' => $rating,
                'id' => $existingId
            ]);
        } else {
            // Создаем новую оценку
            $stmt = $this->db->prepare("
                INSERT INTO ratings (post_id, user_id, rating)
                VALUES (:post_id, :user_id, :rating)
            ");
            
            $result = $stmt->execute([
                'post_id' => $postId,
                'user_id' => $userId,
                'rating' => $rating
            ]);

            return $result ? (int)$this->db->lastInsertId() : false;
        }
    }

    /**
     * Получение ID оценки пользователя для поста
     */
    private function getUserRatingId(int $postId, int $userId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id FROM ratings 
            WHERE post_id = :post_id AND user_id = :user_id
        ");
        $stmt->execute([
            'post_id' => $postId,
            'user_id' => $userId
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['id'] : null;
    }

    /**
     * Получение оценки пользователя для поста
     */
    public function getUserRating(int $postId, int $userId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT rating FROM ratings 
            WHERE post_id = :post_id AND user_id = :user_id
        ");
        $stmt->execute([
            'post_id' => $postId,
            'user_id' => $userId
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['rating'] : null;
    }

    /**
     * Получение среднего рейтинга поста (округление до 1 знака)
     */
    public function getAverageRating(int $postId): float
    {
        $stmt = $this->db->prepare("
            SELECT AVG(rating) as avg_rating FROM ratings 
            WHERE post_id = :post_id
        ");
        $stmt->execute(['post_id' => $postId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['avg_rating'] !== null ? round((float)$result['avg_rating'], 1) : 0.0;
    }

    /**
     * Получение количества оценок для поста
     */
    public function getCount(int $postId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM ratings 
            WHERE post_id = :post_id
        ");
        $stmt->execute(['post_id' => $postId]);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Удаление оценки пользователя
     */
    public function deleteRating(int $postId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM ratings 
            WHERE post_id = :post_id AND user_id = :user_id
        ");
        
        return $stmt->execute([
            'post_id' => $postId,
            'user_id' => $userId
        ]);
    }

    /**
     * Проверка, голосовал ли пользователь за пост
     */
    public function hasRated(int $postId, int $userId): bool
    {
        return $this->getUserRatingId($postId, $userId) !== null;
    }
}
