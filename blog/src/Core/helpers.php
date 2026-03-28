<?php
/**
 * src/Core/helpers.php
 * Глобальные функции-хелперы для приложения.
 */

/**
 * Безопасный вывод данных (защита от XSS)
 * @param string|null $data Данные для вывода
 * @param int $flags Флаги htmlspecialchars
 * @return string
 */
function e(?string $data, int $flags = ENT_QUOTES | ENT_HTML5): string
{
    if ($data === null) {
        return '';
    }
    return htmlspecialchars($data, $flags, 'UTF-8');
}

/**
 * Редирект на указанный URL
 * @param string $url
 * @return void
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/**
 * Проверка, является ли запрос AJAX
 * @return bool
 */
function isAjax(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Получение и возврат JSON ответа
 * @param mixed $data Данные для кодирования
 * @param int $statusCode HTTP статус код
 * @return void
 */
function jsonResponse(mixed $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Форматирование даты
 * @param string $dateString Дата в формате MySQL
 * @param string $format Формат вывода (по умолчанию 'd.m.Y H:i')
 * @return string
 */
function formatDate(string $dateString, string $format = 'd.m.Y H:i'): string
{
    $dateTime = new DateTime($dateString);
    return $dateTime->format($format);
}

/**
 * Форматирование относительного времени (например, "5 минут назад")
 * @param string $dateString Дата в формате MySQL
 * @return string
 */
function timeAgo(string $dateString): string
{
    $dateTime = new DateTime($dateString);
    $now = new DateTime();
    $diff = $now->diff($dateTime);

    $minutes = $diff->i + $diff->h * 60 + $diff->days * 24 * 60;

    if ($minutes < 1) {
        return 'только что';
    } elseif ($minutes < 60) {
        return "$minutes мин. назад";
    } elseif ($diff->h < 24) {
        return "{$diff->h} ч. назад";
    } elseif ($diff->days < 7) {
        return "{$diff->days} дн. назад";
    } else {
        return $dateTime->format('d.m.Y');
    }
}

/**
 * Обрезка текста до определенной длины с добавлением многоточия
 * @param string $text Текст
 * @param int $length Максимальная длина
 * @return string
 */
function truncate(string $text, int $length = 100): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . '...';
}

/**
 * Генерация slug (ЧПУ) из строки
 * @param string $string Исходная строка
 * @return string
 */
function generateSlug(string $string): string
{
    // Транслитерация кириллицы
    $converter = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
        'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
        'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
        'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
        'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'Ch',
        'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
        'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
    ];

    $string = strtr($string, $converter);
    $string = mb_strtolower($string);
    
    // Замена всех не буквенно-цифровых символов на дефис
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    
    // Удаление повторяющихся дефисов
    $string = preg_replace('/-+/', '-', $string);
    
    // Удаление дефисов с краев
    $string = trim($string, '-');
    
    return $string;
}

/**
 * Проверка валидности email
 * @param string $email
 * @return bool
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Получение IP адреса пользователя (с учетом прокси)
 * @return string
 */
function getClientIp(): string
{
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            if (filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                return trim($ip);
            }
        }
    }
    
    return 'unknown';
}

/**
 * Форматирование числа с разделителями тысяч
 * @param int|float $number
 * @return string
 */
function formatNumber(int|float $number): string
{
    return number_format($number, 0, '.', ' ');
}

/**
 * Проверка, что пользователь авторизован (редирект если нет)
 * Используется как обертка над Auth::requireLogin()
 */
function requireLogin(): void
{
    \App\Core\Auth::requireLogin();
}

/**
 * Проверка роли (редирект если нет прав)
 */
function requireRole(int ...$roles): void
{
    \App\Core\Auth::requireRole(...$roles);
}
