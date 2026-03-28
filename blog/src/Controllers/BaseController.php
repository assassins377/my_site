<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Auth;

/**
 * Базовый контроллер.
 * Содержит общие методы для всех контроллеров.
 */
class BaseController
{
    /**
     * Проверка авторизации пользователя.
     * @throws \Exception Если пользователь не авторизован
     */
    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
    }

    /**
     * Проверка роли пользователя.
     * @param array $allowedRoles Массив допустимых ролей (ID)
     * @throws \Exception Если роль не подходит
     */
    protected function requireRole(array $allowedRoles): void
    {
        $this->requireAuth();
        
        $user = Auth::user();
        if (!in_array($user['role_id'], $allowedRoles)) {
            http_response_code(403);
            die('Доступ запрещен: недостаточно прав.');
        }
    }

    /**
     * Проверка CSRF токена.
     * @param array $post Данные POST запроса
     * @throws \Exception Если токен невалиден
     */
    protected function verifyCsrf(array $post): void
    {
        if (!isset($post['csrf_token']) || !\App\Core\Csrf::validate($post['csrf_token'])) {
            http_response_code(403);
            die('Ошибка CSRF токена. Обновите страницу и попробуйте снова.');
        }
    }

    /**
     * Рендеринг представления с данными.
     * @param string $view Путь к представлению (относительно src/Views)
     * @param array $data Данные для передачи в представление
     */
    protected function render(string $view, array $data = []): void
    {
        View::render($view, $data);
    }

    /**
     * Перенаправление на другой URL.
     * @param string $url
     */
    protected function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    /**
     * Возврат JSON ответа.
     * @param array $data
     * @param int $code
     */
    protected function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
