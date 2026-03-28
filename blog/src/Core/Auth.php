<?php
/**
 * src/Core/Auth.php
 * Класс для управления аутентификацией и авторизацией пользователей.
 * Работает с PHP сессиями.
 */

namespace App\Core;

use App\Models\User;

class Auth
{
    /**
     * Проверка, авторизован ли пользователь
     */
    public static function check(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Получение текущего пользователя
     * @return array|null Данные пользователя или null, если не авторизован
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        // Кэшируем пользователя в сессии, чтобы не делать запрос к БД каждый раз
        if (isset($_SESSION['user_data'])) {
            return $_SESSION['user_data'];
        }

        $userId = $_SESSION['user_id'];
        $userModel = new User();
        $user = $userModel->findById($userId);

        if ($user) {
            $_SESSION['user_data'] = $user;
            return $user;
        }

        // Если пользователь не найден в БД, разрушаем сессию
        self::logout();
        return null;
    }

    /**
     * Вход пользователя (логин)
     * @param string $login Логин или email
     * @param string $password Пароль
     * @return bool Успешность входа
     */
    public static function login(string $login, string $password): bool
    {
        $userModel = new User();
        $user = $userModel->findByLoginOrEmail($login);

        if (!$user) {
            return false;
        }

        // Проверка хэша пароля
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Проверка блокировки
        if ($user['is_blocked']) {
            return false;
        }

        // Установка сессии
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_data'] = $user;
        
        // Регенерация ID сессии для защиты от фиксации сессии
        session_regenerate_id(true);

        return true;
    }

    /**
     * Выход пользователя (логаут)
     */
    public static function logout(): void
    {
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        session_destroy();
    }

    /**
     * Проверка роли пользователя
     * @param int ...$roles Список допустимых ID ролей
     * @return bool
     */
    public static function hasRole(int ...$roles): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        return in_array((int)$user['role_id'], $roles, true);
    }

    /**
     * Проверка, является ли пользователь администратором
     */
    public static function isAdmin(): bool
    {
        return self::hasRole(1); // ID роли Admin = 1
    }

    /**
     * Проверка, является ли пользователь модератором или админом
     */
    public static function isModeratorOrAdmin(): bool
    {
        return self::hasRole(1, 2); // ID ролей Admin = 1, Moderator = 2
    }

    /**
     * Проверка, является ли пользователь автором или выше
     */
    public static function isAuthorOrHigher(): bool
    {
        return self::hasRole(1, 2, 3); // Admin, Moderator, Author
    }

    /**
     * Требовать авторизацию (редирект на страницу входа, если не авторизован)
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    /**
     * Требовать определенную роль (редирект на главную, если нет прав)
     * @param int ...$roles
     */
    public static function requireRole(int ...$roles): void
    {
        self::requireLogin();
        
        if (!self::hasRole(...$roles)) {
            http_response_code(403);
            echo "Доступ запрещен. Недостаточно прав.";
            exit;
        }
    }

    /**
     * Получить ID текущего пользователя
     */
    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }
}
