<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;

/**
 * Контроллер рейтинга (AJAX).
 * Обрабатывает выставление оценок постам.
 */
class RatingController extends BaseController
{
    /**
     * Выставление оценки посту (AJAX).
     */
    public function rate(): void
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
        $rating = (int)($_POST['rating'] ?? 0);

        if ($postId <= 0) {
            $this->json(['success' => false, 'message' => 'Неверный ID поста'], 400);
            return;
        }

        if ($rating < 1 || $rating > 10) {
            $this->json(['success' => false, 'message' => 'Рейтинг должен быть от 1 до 10'], 400);
            return;
        }

        $ratingModel = new \App\Models\Rating();

        // Проверка: пользователь еще не голосовал за этот пост
        if ($ratingModel->getUserRating($postId, $_SESSION['user_id'])) {
            $this->json(['success' => false, 'message' => 'Вы уже голосовали за этот пост'], 400);
            return;
        }

        $ratingId = $ratingModel->create([
            'post_id' => $postId,
            'user_id' => $_SESSION['user_id'],
            'rating' => $rating
        ]);

        if ($ratingId) {
            $averageRating = $ratingModel->getAverageRating($postId);
            
            $this->json([
                'success' => true,
                'message' => 'Голос принят',
                'averageRating' => round($averageRating, 1),
                'userRating' => $rating
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Ошибка при сохранении голоса'], 500);
        }
    }
}
