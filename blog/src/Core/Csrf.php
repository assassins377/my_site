<?php
/**
 * src/Core/Csrf.php
 * Класс для защиты от CSRF-атак (Cross-Site Request Forgery).
 * Генерация и проверка токенов.
 */

namespace App\Core;

class Csrf
{
    private const TOKEN_KEY = 'csrf_token';
    private const TOKEN_TIME_KEY = 'csrf_token_time';
    private const TOKEN_LIFETIME = 3600; // Время жизни токена в секундах (1 час)

    /**
     * Генерация нового CSRF токена
     * @return string
     */
    public static function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_KEY] = $token;
        $_SESSION[self::TOKEN_TIME_KEY] = time();
        return $token;
    }

    /**
     * Получение текущего токена (если есть, иначе генерация нового)
     * @return string
     */
    public static function getToken(): string
    {
        if (!isset($_SESSION[self::TOKEN_KEY]) || self::isTokenExpired()) {
            return self::generateToken();
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    /**
     * Проверка токена
     * @param string|null $token Токен для проверки
     * @return bool
     */
    public static function verify(?string $token): bool
    {
        if (!$token || !isset($_SESSION[self::TOKEN_KEY])) {
            return false;
        }

        // Проверка времени жизни токена
        if (self::isTokenExpired()) {
            self::regenerateToken();
            return false;
        }

        // Сравнение токенов с использованием hash_equals для защиты от timing attacks
        return hash_equals($_SESSION[self::TOKEN_KEY], $token);
    }

    /**
     * Проверка токена из POST запроса
     * @return bool
     */
    public static function verifyPost(): bool
    {
        $token = $_POST['csrf_token'] ?? null;
        return self::verify($token);
    }

    /**
     * Проверка токена из заголовка (для AJAX запросов)
     * @return bool
     */
    public static function verifyHeader(): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return self::verify($token);
    }

    /**
     * Проверка истечения срока действия токена
     * @return bool
     */
    private static function isTokenExpired(): bool
    {
        if (!isset($_SESSION[self::TOKEN_TIME_KEY])) {
            return true;
        }
        return (time() - $_SESSION[self::TOKEN_TIME_KEY]) > self::TOKEN_LIFETIME;
    }

    /**
     * Регенерация токена (после использования или по истечении времени)
     * @return string Новый токен
     */
    public static function regenerateToken(): string
    {
        return self::generateToken();
    }

    /**
     * Удаление токена (например, после выхода)
     */
    public static function clearToken(): void
    {
        unset($_SESSION[self::TOKEN_KEY]);
        unset($_SESSION[self::TOKEN_TIME_KEY]);
    }

    /**
     * Получить HTML input скрытого поля с токеном
     * @return string
     */
    public static function inputField(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Получить мета-тег для AJAX запросов
     * @return string
     */
    public static function metaTag(): string
    {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
