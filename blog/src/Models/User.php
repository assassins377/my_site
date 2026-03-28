<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Модель пользователя
 * Работа с таблицей users, аутентификация, управление профилем
 */
class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Поиск пользователя по email или логину
     */
    public function findByLoginOrEmail(string $loginOrEmail): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.email = :val OR u.login = :val
        ");
        $stmt->execute(['val' => $loginOrEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ?: null;
    }

    /**
     * Поиск пользователя по ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ?: null;
    }

    /**
     * Создание нового пользователя
     */
    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (login, email, password_hash, role_id)
            VALUES (:login, :email, :password_hash, :role_id)
        ");
        
        $result = $stmt->execute([
            'login' => $data['login'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role_id' => $data['role_id'] ?? 4 // По умолчанию Reader
        ]);

        return $result ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Обновление профиля пользователя
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (isset($data['login'])) {
            $fields[] = "login = :login";
            $params['login'] = $data['login'];
        }
        if (isset($data['email'])) {
            $fields[] = "email = :email";
            $params['email'] = $data['email'];
        }
        if (isset($data['password'])) {
            $fields[] = "password_hash = :password_hash";
            $params['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (empty($fields)) {
            return true;
        }

        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Блокировка/разблокировка пользователя (Admin)
     */
    public function toggleBlock(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET is_blocked = NOT is_blocked, updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Смена роли пользователя (Admin)
     */
    public function changeRole(int $userId, int $roleId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET role_id = :role_id, updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute(['role_id' => $roleId, 'id' => $userId]);
    }

    /**
     * Удаление пользователя (каскадное удаление через БД)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Получение списка всех пользователей (для админки)
     */
    public function getAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Подсчет общего количества пользователей
     */
    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        return (int)$stmt->fetchColumn();
    }
}
