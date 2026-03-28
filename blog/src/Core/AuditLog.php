<?php
/**
 * src/Core/AuditLog.php
 * Класс для логирования действий администраторов (Audit Log).
 * Запись в таблицу admin_logs.
 */

namespace App\Core;

use App\Core\Database;

class AuditLog
{
    /**
     * Логирование действия администратора
     * 
     * @param string $action Описание действия (например, "UPDATE_USER", "DELETE_POST")
     * @param string $tableName Имя таблицы, над которой производилось действие
     * @param int|null $recordId ID записи, над которой производилось действие
     * @param array $extraData Дополнительные данные (старые/новые значения и т.д.)
     * @return bool Успешность записи
     */
    public static function log(
        string $action,
        string $tableName,
        ?int $recordId = null,
        array $extraData = []
    ): bool {
        // Получаем текущего пользователя (должен быть админом или модератором)
        $user = Auth::user();
        if (!$user) {
            return false; // Не логируем действия неавторизованных пользователей
        }

        $db = Database::getInstance();
        
        $adminUserId = $user['id'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $details = !empty($extraData) ? json_encode($extraData, JSON_UNESCAPED_UNICODE) : null;

        $sql = "INSERT INTO admin_logs (admin_user_id, action, table_name, record_id, ip_address, user_agent, details, created_at) 
                VALUES (:admin_user_id, :action, :table_name, :record_id, :ip_address, :user_agent, :details, NOW())";

        $params = [
            ':admin_user_id' => $adminUserId,
            ':action' => $action,
            ':table_name' => $tableName,
            ':record_id' => $recordId,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':details' => $details,
        ];

        $result = $db->query($sql, $params);
        
        if ($result === null) {
            error_log("Не удалось записать audit log: $action");
            return false;
        }

        return true;
    }

    /**
     * Получение списка логов с пагинацией
     * 
     * @param int $page Номер страницы
     * @param int $perPage Количество записей на страницу
     * @param string|null $filterAction Фильтр по типу действия
     * @param int|null $filterAdminId Фильтр по ID администратора
     * @return array ['logs' => [...], 'total' => int, 'pages' => int]
     */
    public static function getLogs(
        int $page = 1,
        int $perPage = 20,
        ?string $filterAction = null,
        ?int $filterAdminId = null
    ): array {
        $db = Database::getInstance();
        $offset = ($page - 1) * $perPage;

        $whereConditions = [];
        $params = [];

        if ($filterAction) {
            $whereConditions[] = "al.action LIKE :action";
            $params[':action'] = "%$filterAction%";
        }

        if ($filterAdminId) {
            $whereConditions[] = "al.admin_user_id = :admin_id";
            $params[':admin_id'] = $filterAdminId;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Получение общего количества записей
        $countSql = "SELECT COUNT(*) as total FROM admin_logs al $whereClause";
        $countResult = $db->fetchOne($countSql, $params);
        $total = (int)($countResult['total'] ?? 0);
        $pages = ceil($total / $perPage);

        // Получение записей
        $sql = "SELECT al.*, u.login as admin_login, u.email as admin_email
                FROM admin_logs al
                LEFT JOIN users u ON al.admin_user_id = u.id
                $whereClause
                ORDER BY al.created_at DESC
                LIMIT :limit OFFSET :offset";

        // Добавляем параметры лимита и оффсета
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $logs = $db->fetchAll($sql, $params);

        return [
            'logs' => $logs,
            'total' => $total,
            'pages' => $pages,
            'current_page' => $page,
        ];
    }

    /**
     * Очистка старых логов (по истечении определенного периода)
     * 
     * @param int $daysToDelete Количество дней, после которых логи удаляются
     * @return int Количество удаленных записей
     */
    public static function cleanOldLogs(int $daysToDelete = 90): int
    {
        $db = Database::getInstance();
        
        $sql = "DELETE FROM admin_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $params = [':days' => $daysToDelete];
        
        $stmt = $db->query($sql, $params);
        
        return $stmt ? $stmt->rowCount() : 0;
    }

    /**
     * Получение статистики по действиям
     * 
     * @return array Массив с количеством действий по типам
     */
    public static function getActionStats(): array
    {
        $db = Database::getInstance();
        
        $sql = "SELECT action, COUNT(*) as count 
                FROM admin_logs 
                GROUP BY action 
                ORDER BY count DESC";
        
        return $db->fetchAll($sql);
    }
}
