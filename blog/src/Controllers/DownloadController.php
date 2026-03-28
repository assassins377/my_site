<?php

namespace App\Controllers;

use App\Core\Auth;

/**
 * Контроллер скачивания торрентов.
 * Обеспечивает безопасную загрузку файлов извне public/.
 */
class DownloadController extends BaseController
{
    /**
     * Скачивание торрент-файла.
     * @param int $id ID поста
     */
    public function torrent(int $id): void
    {
        $postModel = new \App\Models\Post();
        $post = $postModel->findById($id);

        if (!$post || empty($post['torrent_file'])) {
            http_response_code(404);
            die('Торрент файл не найден');
        }

        // Путь к файлу (вне public/)
        $filePath = __DIR__ . '/../../storage/' . $post['torrent_file'];

        if (!file_exists($filePath)) {
            http_response_code(404);
            die('Файл не найден на сервере');
        }

        // Логирование скачивания
        if (Auth::check()) {
            $userId = $_SESSION['user_id'];
        } else {
            $userId = null;
        }

        $this->logDownload($id, $userId);

        // Отправка файла
        header('Content-Type: application/x-bittorrent');
        header('Content-Disposition: attachment; filename="' . basename($post['torrent_file']) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('X-Accel-Redirect: /protected/' . $post['torrent_file']); // Для Nginx X-Accel
        
        readfile($filePath);
        exit;
    }

    /**
     * Логирование скачивания торрента.
     */
    private function logDownload(int $postId, ?int $userId): void
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        try {
            $db = \App\Core\Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                INSERT INTO admin_logs (admin_user_id, action, table_name, record_id, ip_address, user_agent, created_at)
                VALUES (:user_id, :action, :table_name, :record_id, :ip, :ua, NOW())
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'action' => 'download_torrent',
                'table_name' => 'posts',
                'record_id' => $postId,
                'ip' => $ipAddress,
                'ua' => $userAgent
            ]);
        } catch (\Exception $e) {
            error_log("Download log error: " . $e->getMessage());
        }
    }
}
