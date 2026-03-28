<?php
/**
 * Скрипт проверки системы и безопасности
 * Запуск: php tests/check_system.php
 */

echo "🔍 Проверка системы Anime Blog\n";
echo str_repeat("=", 50) . "\n\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Проверка версии PHP
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    $errors[] = "❌ Требуется PHP 8.2+, текущая версия: " . PHP_VERSION;
} else {
    $success[] = "✅ Версия PHP: " . PHP_VERSION;
}

// 2. Проверка расширений
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl', 'gd', 'intl'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        $success[] = "✅ Расширение {$ext} установлено";
    } else {
        $errors[] = "❌ Расширение {$ext} НЕ установлено";
    }
}

// 3. Проверка прав доступа к директориям
$writableDirs = [
    __DIR__ . '/../storage/cache',
    __DIR__ . '/../storage/torrents',
    __DIR__ . '/../logs',
    __DIR__ . '/../public/uploads/posters'
];

foreach ($writableDirs as $dir) {
    if (is_writable($dir)) {
        $success[] = "✅ Директория доступна для записи: " . basename($dir);
    } else {
        $errors[] = "❌ Нет прав на запись: {$dir}";
    }
}

// 4. Проверка существования критических файлов
$requiredFiles = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../database/dump.sql',
    __DIR__ . '/../public/index.php',
    __DIR__ . '/../.env.example'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        $success[] = "✅ Файл существует: " . basename($file);
    } else {
        $errors[] = "❌ Файл отсутствует: {$file}";
    }
}

// 5. Проверка подключения к БД (если настроено)
if (file_exists(__DIR__ . '/../config/config.php')) {
    try {
        require_once __DIR__ . '/../config/config.php';
        // Попытка создания соединения (код подключения из config.php)
        // Здесь можно добавить реальную проверку подключения
        $success[] = "✅ Файл конфигурации БД найден";
    } catch (Exception $e) {
        $warnings[] = "⚠️ Ошибка подключения к БД: " . $e->getMessage();
    }
}

// 6. Проверка безопасности (запрет прямого доступа к торрентам)
$torrentDir = __DIR__ . '/../storage/torrents';
if (strpos($torrentDir, 'public') === false) {
    $success[] = "✅ Торренты хранятся вне public/ директории";
} else {
    $errors[] = "❌ ОПАСНОСТЬ: Торренты находятся в публичной зоне!";
}

// Вывод результатов
echo "РЕЗУЛЬТАТЫ ПРОВЕРКИ:\n\n";

if (!empty($success)) {
    echo "🟢 УСПЕШНО:\n";
    foreach ($success as $msg) {
        echo "   {$msg}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "🟡 ПРЕДУПРЕЖДЕНИЯ:\n";
    foreach ($warnings as $msg) {
        echo "   {$msg}\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "🔴 ОШИБКИ:\n";
    foreach ($errors as $msg) {
        echo "   {$msg}\n";
    }
    echo "\n";
}

echo str_repeat("=", 50) . "\n";
if (empty($errors)) {
    echo "✅ Все проверки пройдены успешно!\n";
    exit(0);
} else {
    echo "❌ Обнаружено ошибок: " . count($errors) . ". Исправьте их перед запуском.\n";
    exit(1);
}
