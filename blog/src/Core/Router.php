<?php
/**
 * src/Core/Router.php
 * Простой роутер для обработки URL и вызова контроллеров.
 * Поддерживает GET, POST, PUT, DELETE методы.
 */

namespace App\Core;

class Router
{
    private array $routes = [];

    /**
     * Регистрация маршрута для метода GET
     */
    public function get(string $path, array $controller): self
    {
        $this->routes['GET'][$path] = $controller;
        return $this;
    }

    /**
     * Регистрация маршрута для метода POST
     */
    public function post(string $path, array $controller): self
    {
        $this->routes['POST'][$path] = $controller;
        return $this;
    }

    /**
     * Регистрация маршрута для метода PUT
     */
    public function put(string $path, array $controller): self
    {
        $this->routes['PUT'][$path] = $controller;
        return $this;
    }

    /**
     * Регистрация маршрута для метода DELETE
     */
    public function delete(string $path, array $controller): self
    {
        $this->routes['DELETE'][$path] = $controller;
        return $this;
    }

    /**
     * Обработка текущего запроса
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Убираем trailing slash, кроме корня
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        // Проверка наличия маршрута
        if (!isset($this->routes[$method][$uri])) {
            // Если метод не найден, проверяем, есть ли такой путь для других методов (для ошибки 405)
            $pathExists = false;
            foreach ($this->routes as $m => $paths) {
                if (isset($paths[$uri])) {
                    $pathExists = true;
                    break;
                }
            }
            
            if ($pathExists) {
                http_response_code(405); // Method Not Allowed
                echo "Метод $method не разрешен для этого URL.";
                return;
            }

            http_response_code(404);
            // Можно подключить view для 404 страницы
            echo "Страница не найдена (404)";
            return;
        }

        [$controllerClass, $action] = $this->routes[$method][$uri];

        // Проверка существования класса контроллера
        if (!class_exists($controllerClass)) {
            throw new \Exception("Контроллер $controllerClass не найден");
        }

        $controller = new $controllerClass();

        // Проверка существования метода действия
        if (!method_exists($controller, $action)) {
            throw new \Exception("Действие $action не найдено в контроллере $controllerClass");
        }

        // Вызов метода контроллера
        call_user_func([$controller, $action]);
    }

    /**
     * Генерация URL по имени маршрута (упрощенная реализация)
     * В полной версии можно использовать именованные маршруты
     */
    public static function url(string $path): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? '';
        return $baseUrl . '/' . ltrim($path, '/');
    }
}
