<?php
/**
 * public/index.php
 * Точка входа для всех HTTP запросов.
 * Подключает конфигурацию и запускает роутер.
 */

// Подключаем основной конфиг (загружает .env, автозагрузку, хелперы)
require_once dirname(__DIR__) . '/config/config.php';

use App\Core\Router;

// Создаем экземпляр роутера
$router = new Router();

// ============================================================================
// МАРШРУТЫ (ROUTES)
// ============================================================================

// Главная страница
$router->get('/', [\App\Controllers\HomeController::class, 'index']);

// Поиск
$router->get('/search', [\App\Controllers\SearchController::class, 'search']);

// Посты (Anime)
$router->get('/post/{slug}', [\App\Controllers\PostController::class, 'show']);
$router->get('/posts', [\App\Controllers\PostController::class, 'index']);

// Авторизация / Регистрация
$router->get('/login', [\App\Controllers\AuthController::class, 'showLogin']);
$router->post('/login', [\App\Controllers\AuthController::class, 'login']);
$router->get('/register', [\App\Controllers\AuthController::class, 'showRegister']);
$router->post('/register', [\App\Controllers\AuthController::class, 'register']);
$router->get('/logout', [\App\Controllers\AuthController::class, 'logout']);

// Профиль пользователя
$router->get('/profile', [\App\Controllers\ProfileController::class, 'index']);
$router->get('/profile/edit', [\App\Controllers\ProfileController::class, 'edit']);
$router->post('/profile/update', [\App\Controllers\ProfileController::class, 'update']);
$router->get('/profile/bookmarks', [\App\Controllers\ProfileController::class, 'bookmarks']);

// Комментарии
$router->post('/comment/create', [\App\Controllers\CommentController::class, 'create']);
$router->post('/comment/update', [\App\Controllers\CommentController::class, 'update']);
$router->post('/comment/delete', [\App\Controllers\CommentController::class, 'delete']);
$router->post('/comment/report', [\App\Controllers\CommentController::class, 'report']);

// Рейтинг (AJAX)
$router->post('/rating/set', [\App\Controllers\RatingController::class, 'set']);

// Закладки (AJAX)
$router->post('/bookmark/toggle', [\App\Controllers\BookmarkController::class, 'toggle']);

// Жалобы
$router->post('/report/create', [\App\Controllers\ReportController::class, 'create']);

// Обратная связь
$router->get('/feedback', [\App\Controllers\FeedbackController::class, 'show']);
$router->post('/feedback/send', [\App\Controllers\FeedbackController::class, 'send']);

// Помощь проекту
$router->get('/help', [\App\Controllers\HelpController::class, 'index']);

// Скачивание торрентов (через контроллер)
$router->get('/download/{id}', [\App\Controllers\DownloadController::class, 'download']);

// Админ-панель
$router->get('/admin', [\App\Controllers\Admin\DashboardController::class, 'index']);
$router->get('/admin/users', [\App\Controllers\Admin\UserController::class, 'index']);
$router->post('/admin/users/block', [\App\Controllers\Admin\UserController::class, 'block']);
$router->post('/admin/users/role', [\App\Controllers\Admin\UserController::class, 'changeRole']);
$router->post('/admin/users/delete', [\App\Controllers\Admin\UserController::class, 'delete']);

$router->get('/admin/posts', [\App\Controllers\Admin\PostController::class, 'index']);
$router->get('/admin/posts/create', [\App\Controllers\Admin\PostController::class, 'create']);
$router->post('/admin/posts/store', [\App\Controllers\Admin\PostController::class, 'store']);
$router->get('/admin/posts/edit/{id}', [\App\Controllers\Admin\PostController::class, 'edit']);
$router->post('/admin/posts/update', [\App\Controllers\Admin\PostController::class, 'update']);
$router->post('/admin/posts/delete', [\App\Controllers\Admin\PostController::class, 'delete']);

$router->get('/admin/comments', [\App\Controllers\Admin\CommentController::class, 'index']);
$router->post('/admin/comments/approve', [\App\Controllers\Admin\CommentController::class, 'approve']);
$router->post('/admin/comments/reject', [\App\Controllers\Admin\CommentController::class, 'reject']);

$router->get('/admin/reports', [\App\Controllers\Admin\ReportController::class, 'index']);
$router->post('/admin/reports/process', [\App\Controllers\Admin\ReportController::class, 'process']);

$router->get('/admin/feedback', [\App\Controllers\Admin\FeedbackController::class, 'index']);
$router->post('/admin/feedback/respond', [\App\Controllers\Admin\FeedbackController::class, 'respond']);

$router->get('/admin/logs', [\App\Controllers\Admin\LogController::class, 'index']);

$router->get('/admin/settings', [\App\Controllers\Admin\SettingsController::class, 'index']);
$router->post('/admin/settings/update', [\App\Controllers\Admin\SettingsController::class, 'update']);

// SEO Feeds
$router->get('/sitemap.xml', [\App\Controllers\FeedController::class, 'sitemap']);
$router->get('/feed.xml', [\App\Controllers\FeedController::class, 'rss']);

// ============================================================================
// ОБРАБОТКА ЗАПРОСА
// ============================================================================

try {
    $router->dispatch();
} catch (\Exception $e) {
    // Логирование ошибки
    error_log("Ошибка приложения: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Вывод страницы ошибки
    if ($_ENV['APP_ENV'] === 'development') {
        echo "<pre>Ошибка: " . htmlspecialchars($e->getMessage()) . "\n";
        echo "Файл: " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</pre>";
    } else {
        http_response_code(500);
        echo "Произошла внутренняя ошибка сервера. Пожалуйста, попробуйте позже.";
    }
}
