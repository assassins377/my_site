<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Модель категории
 * Работа с таблицей categories
 */
class Category
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Получение всех категорий
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM categories ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получение категории по ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $category ?: null;
    }

    /**
     * Создание категории (Admin)
     */
    public function create(string $name, string $slug): int|false
    {
        $stmt = $this->db->prepare("
            INSERT INTO categories (name, slug)
            VALUES (:name, :slug)
        ");
        
        $result = $stmt->execute([
            'name' => $name,
            'slug' => $slug
        ]);

        return $result ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Обновление категории (Admin)
     */
    public function update(int $id, string $name, string $slug): bool
    {
        $stmt = $this->db->prepare("
            UPDATE categories 
            SET name = :name, slug = :slug, updated_at = NOW()
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'id' => $id
        ]);
    }

    /**
     * Удаление категории (Admin)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM categories WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Подсчет постов в категории
     */
    public function countPosts(int $categoryId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM posts WHERE category_id = :id
        ");
        $stmt->execute(['id' => $categoryId]);
        return (int)$stmt->fetchColumn();
    }
}
