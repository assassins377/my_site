<?php

namespace App\Controllers;

use App\Models\Comment;
use App\Core\Auth;
use App\Core\Csrf;

/**
 * Контроллер комментариев.
 * Обрабатывает создание, редактирование, удаление комментариев и жалобы.
 */
class CommentController extends BaseController
{
    private Comment $commentModel;

    public function __construct()
    {
        $this->commentModel = new Comment();
    }

    /**
     * Создание комментария.
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf($_POST);

        $postId = (int)($_POST['post_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if ($postId <= 0) {
            $_SESSION['error'] = 'Неверный ID поста';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
            return;
        }

        if (empty($content)) {
            $_SESSION['error'] = 'Комментарий не может быть пустым';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
            return;
        }

        if (strlen($content) > 5000) {
            $_SESSION['error'] = 'Комментарий слишком длинный (макс. 5000 символов)';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
            return;
        }

        $commentId = $this->commentModel->create([
            'post_id' => $postId,
            'user_id' => $_SESSION['user_id'],
            'content' => $content,
            'status' => 'pending' // По умолчанию ожидает модерации
        ]);

        if ($commentId) {
            $_SESSION['success'] = 'Комментарий отправлен на модерацию';
        } else {
            $_SESSION['error'] = 'Ошибка при создании комментария';
        }

        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/post/' . $postId);
    }

    /**
     * Редактирование комментария.
     * Доступно только автору в течение 15 минут после создания.
     */
    public function edit(int $id): void
    {
        $this->requireAuth();

        $comment = $this->commentModel->findById($id);
        if (!$comment) {
            http_response_code(404);
            die('Комментарий не найден');
        }

        // Проверка прав: только автор может редактировать
        if ($comment['user_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            die('Доступ запрещен: вы можете редактировать только свои комментарии');
        }

        // Проверка времени: можно редактировать только в течение 15 минут
        $createdAt = strtotime($comment['created_at']);
        $now = time();
        $timeDiff = $now - $createdAt;

        if ($timeDiff > 900) { // 15 минут = 900 секунд
            $_SESSION['error'] = 'Время для редактирования истекло (15 минут)';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
            return;
        }

        $this->render('comments/edit', [
            'comment' => $comment,
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Обновление комментария.
     */
    public function update(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf($_POST);

        $comment = $this->commentModel->findById($id);
        if (!$comment) {
            http_response_code(404);
            die('Комментарий не найден');
        }

        // Проверка прав
        if ($comment['user_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            die('Доступ запрещен');
        }

        // Проверка времени
        $createdAt = strtotime($comment['created_at']);
        $now = time();
        $timeDiff = $now - $createdAt;

        if ($timeDiff > 900) {
            $_SESSION['error'] = 'Время для редактирования истекло (15 минут)';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
            return;
        }

        $content = trim($_POST['content'] ?? '');

        if (empty($content)) {
            $_SESSION['error'] = 'Комментарий не может быть пустым';
            $this->redirect('/comments/' . $id . '/edit');
            return;
        }

        if (strlen($content) > 5000) {
            $_SESSION['error'] = 'Комментарий слишком длинный';
            $this->redirect('/comments/' . $id . '/edit');
            return;
        }

        $updated = $this->commentModel->update($id, [
            'content' => $content,
            'status' => 'pending' // После редактирования снова на модерацию
        ]);

        if ($updated) {
            $_SESSION['success'] = 'Комментарий обновлен и отправлен на повторную модерацию';
        } else {
            $_SESSION['error'] = 'Ошибка при обновлении комментария';
        }

        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    /**
     * Удаление комментария.
     * Доступно только автору в течение 15 минут после создания.
     */
    public function delete(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf($_POST);

        $comment = $this->commentModel->findById($id);
        if (!$comment) {
            http_response_code(404);
            die('Комментарий не найден');
        }

        // Проверка прав
        if ($comment['user_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            die('Доступ запрещен: вы можете удалять только свои комментарии');
        }

        // Проверка времени
        $createdAt = strtotime($comment['created_at']);
        $now = time();
        $timeDiff = $now - $createdAt;

        if ($timeDiff > 900) {
            $_SESSION['error'] = 'Время для удаления истекло (15 минут)';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
            return;
        }

        $deleted = $this->commentModel->delete($id);

        if ($deleted) {
            $_SESSION['success'] = 'Комментарий удален';
        } else {
            $_SESSION['error'] = 'Ошибка при удалении комментария';
        }

        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    /**
     * Создание жалобы на комментарий.
     */
    public function report(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf($_POST);

        $comment = $this->commentModel->findById($id);
        if (!$comment) {
            http_response_code(404);
            die('Комментарий не найден');
        }

        $reason = trim($_POST['reason'] ?? '');
        if (empty($reason)) {
            $_SESSION['error'] = 'Укажите причину жалобы';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
            return;
        }

        if (strlen($reason) > 500) {
            $_SESSION['error'] = 'Причина слишком длинная (макс. 500 символов)';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
            return;
        }

        $reportModel = new \App\Models\Report();
        
        // Проверка: пользователь еще не жаловался на этот комментарий
        if ($reportModel->exists('comment', $id, $_SESSION['user_id'])) {
            $_SESSION['error'] = 'Вы уже отправляли жалобу на этот комментарий';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
            return;
        }

        $reportId = $reportModel->create([
            'reportable_type' => 'comment',
            'reportable_id' => $id,
            'user_id' => $_SESSION['user_id'],
            'reason' => $reason,
            'status' => 'pending'
        ]);

        if ($reportId) {
            $_SESSION['success'] = 'Жалоба отправлена на рассмотрение';
        } else {
            $_SESSION['error'] = 'Ошибка при отправке жалобы';
        }

        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}
