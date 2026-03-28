<?php
/**
 * config/config.php
 * Точка входа приложения. Инициализация окружения, автозагрузка классов и хелперов.
 */

// Определяем корень проекта (на уровень выше папки config)
define('ROOT_PATH', dirname(__DIR__));

// Проверка существования файла .env (для безопасности)
if (!file_exists(ROOT_PATH . '/.env')) {
    die('Ошибка: Файл .env не найден. Скопируйте .env.example в .env и настройте параметры.');
}

// Подключаем Composer Autoload
require_once ROOT_PATH . '/vendor/autoload.php';

// Загружаем переменные окружения из .env
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->load();

// Подключаем глобальные хелперы
require_once ROOT_PATH . '/src/Core/helpers.php';

// Настройка отображения ошибок (в продакшене скрыть)
if ($_ENV['APP_ENV'] === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    // Логируем ошибки в файл
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/logs/error.log');
}

// Настройка временной зоны
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

// Старт сессии (если еще не начата)
if (session_status() === PHP_SESSION_NONE) {
    // Настройки безопасности сессии
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Установить 1 при использовании HTTPS
    
    session_start();
}

// Константы для путей
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
define('TORRENT_PATH', ROOT_PATH . '/storage/torrents');
define('CACHE_PATH', ROOT_PATH . '/storage/cache');

// Проверка и создание директорий для логов и кэша, если их нет
$dirsToCheck = [ROOT_PATH . '/logs', CACHE_PATH, TORRENT_PATH];
foreach ($dirsToCheck as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Создание файла лога ошибок, если его нет
$logFile = ROOT_PATH . '/logs/error.log';
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0644);
}

// Автозагрузка классов ядра (можно заменить на PSR-4 в composer.json, но для наглядности оставим так)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = ROOT_PATH . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Инициализация основных компонентов (можно вынести в контейнер, но пока так)
// Глобальный объект БД будет создаваться по требованию внутри классов
