<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Модель поста (Anime)
 * Работа с таблицей posts, включая title_en для SEO и всплывающих подсказок
 */
class Post
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Получение поста по ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, u.login as author_login
            FROM posts p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $post ?: null;
    }

    /**
     * Получение поста по slug
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, u.login as author_login
            FROM posts p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.slug = :slug
        ");
        $stmt->execute(['slug' => $slug]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $post ?: null;
    }

    /**
     * Создание нового поста
     * @param array $data Данные поста (title, title_en, description, content, etc.)
     * @return int|false ID созданного поста или false при ошибке
     */
    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare("
            INSERT INTO posts 
            (title, title_en, slug, description, content, year, episodes, status, 
             poster_image, user_id, category_id)
            VALUES 
            (:title, :title_en, :slug, :description, :content, :year, :episodes, 
             :status, :poster_image, :user_id, :category_id)
        ");
        
        $result = $stmt->execute([
            'title' => $data['title'],
            'title_en' => $data['title_en'], // Обязательно для Admin
            'slug' => $data['slug'],
            'description' => $data['description'],
            'content' => $data['content'],
            'year' => $data['year'] ?? null,
            'episodes' => $data['episodes'] ?? null,
            'status' => $data['status'] ?? 'ongoing',
            'poster_image' => $data['poster_image'] ?? null,
            'user_id' => $data['user_id'],
            'category_id' => $data['category_id']
        ]);

        return $result ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Обновление поста
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'title', 'title_en', 'slug', 'description', 'content', 
            'year', 'episodes', 'status', 'poster_image', 'category_id'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return true;
        }

        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE posts SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Удаление поста (каскадное удаление связанных данных)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM posts WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Увеличение счетчика просмотров
     */
    public function incrementViews(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE posts SET views = views + 1 WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Получение списка постов с пагинацией
     */
    public function getAll(int $limit = 12, int $offset = 0, ?int $categoryId = null): array
    {
        $where = '';
        $params = [];

        if ($categoryId !== null) {
            $where = "WHERE p.category_id = :category_id";
            $params['category_id'] = $categoryId;
        }

        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name
            FROM posts p
            LEFT JOIN categories c ON p.category_id = c.id
            $where
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Поиск постов по заголовку (RU/EN) и контенту
     */
    public function search(string $query, int $limit = 20, int $offset = 0): array
    {
        $searchTerm = "%{$query}%";
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name
            FROM posts p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.title LIKE :term 
               OR p.title_en LIKE :term 
               OR p.content LIKE :term
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':term', $searchTerm);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Подсчет общего количества постов
     */
    public function count(?int $categoryId = null): int
    {
        if ($categoryId === null) {
            $stmt = $this->db->query("SELECT COUNT(*) FROM posts");
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM posts WHERE category_id = :id");
            $stmt->execute(['id' => $categoryId]);
        }
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Получение постов по категории
     */
    public function getByCategory(int $categoryId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*
            FROM posts p
            WHERE p.category_id = :category_id
            ORDER BY p.created_at DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получение популярных постов (по просмотрам)
     */
    public function getPopular(int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*
            FROM posts p
            ORDER BY p.views DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получение постов автора
     */
    public function getByAuthor(int $userId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*
            FROM posts p
            WHERE p.user_id = :user_id
            ORDER BY p.created_at DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
