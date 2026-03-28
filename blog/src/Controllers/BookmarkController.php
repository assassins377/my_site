<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;

/**
 * Контроллер закладок (AJAX).
 * Обрабатывает добавление/удаление закладок.
 */
class BookmarkController extends BaseController
{
    /**
     * Переключение состояния закладки (AJAX).
     */
    public function toggle(): void
    {
        $this->requireAuth();
        
        // Проверка AJAX запроса
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Неверный тип запроса']);
            return;
        }

        $this->verifyCsrf($_POST);

        $postId = (int)($_POST['post_id'] ?? 0);

        if ($postId <= 0) {
            $this->json(['success' => false, 'message' => 'Неверный ID поста'], 400);
            return;
        }

        $bookmarkModel = new \App\Models\Bookmark();
        $userId = $_SESSION['user_id'];

        // Проверка существования закладки
        $existingBookmark = $bookmarkModel->isBookmarked($postId, $userId);

        if ($existingBookmark) {
            // Удаление закладки
            $deleted = $bookmarkModel->deleteByPostAndUser($postId, $userId);
            
            if ($deleted) {
                $this->json([
                    'success' => true,
                    'action' => 'removed',
                    'message' => 'Закладка удалена',
                    'isBookmarked' => false
                ]);
            } else {
                $this->json(['success' => false, 'message' => 'Ошибка при удалении закладки'], 500);
            }
        } else {
            // Добавление закладки
            $bookmarkId = $bookmarkModel->create([
                'post_id' => $postId,
                'user_id' => $userId
            ]);

            if ($bookmarkId) {
                $this->json([
                    'success' => true,
                    'action' => 'added',
                    'message' => 'Закладка добавлена',
                    'isBookmarked' => true
                ]);
            } else {
                $this->json(['success' => false, 'message' => 'Ошибка при добавлении закладки'], 500);
            }
        }
    }
}
