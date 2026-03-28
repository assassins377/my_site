<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Feedback;
use App\Models\Report;
use App\Models\Donation;
use App\Core\Auth;
use App\Core\Csrf;

/**
 * Админ-панель.
 * Контроллер для управления сайтом (Admin/Moderator).
 */
class AdminController extends BaseController
{
    /**
     * Проверка прав администратора/модератора.
     */
    public function __construct()
    {
        $this->requireRole([1, 2]); // Admin или Moderator
    }

    /**
     * Дашборд администратора.
     */
    public function dashboard(): void
    {
        $db = \App\Core\Database::getInstance()->getConnection();

        // Статистика
        $stats = [];
        
        $stats['total_posts'] = $db->query("SELECT COUNT(*) FROM posts")->fetchColumn();
        $stats['total_users'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['total_comments'] = $db->query("SELECT COUNT(*) FROM comments")->fetchColumn();
        $stats['total_views'] = $db->query("SELECT SUM(views) FROM posts")->fetchColumn() ?: 0;
        $stats['total_donations'] = $db->query("SELECT SUM(amount) FROM donations WHERE status = 'completed'")->fetchColumn() ?: 0;

        // Последние посты
        $stmt = $db->query("SELECT id, title, views, created_at FROM posts ORDER BY created_at DESC LIMIT 5");
        $stats['recent_posts'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Последние пользователи
        $stmt = $db->query("SELECT id, login, email, created_at FROM users ORDER BY created_at DESC LIMIT 5");
        $stats['recent_users'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Ожидающие модерации комментарии
        $stmt = $db->prepare("
            SELECT c.id, c.content, c.created_at, u.login, p.title as post_title
            FROM comments c
            JOIN users u ON c.user_id = u.id
            JOIN posts p ON c.post_id = p.id
            WHERE c.status = 'pending'
            ORDER BY c.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $stats['pending_comments'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Новые жалобы
        $stmt = $db->prepare("
            SELECT r.id, r.reportable_type, r.reason, r.created_at, u.login
            FROM reports r
            JOIN users u ON r.user_id = u.id
            WHERE r.status = 'pending'
            ORDER BY r.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $stats['pending_reports'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $csrfToken = Csrf::generate();

        $this->render('admin/dashboard', [
            'stats' => $stats,
            'csrf_token' => $csrfToken
        ]);
    }

    /**
     * Управление пользователями.
     */
    public function users(): void
    {
        $db = \App\Core\Database::getInstance()->getConnection();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $stmt = $db->prepare("
            SELECT u.*, r.name as role_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $total = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalPages = ceil($total / $limit);

        $this->render('admin/users', [
            'users' => $users,
            'pagination' => ['current' => $page, 'total' => $totalPages],
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Блокировка/разблокировка пользователя.
     */
    public function toggleBlock(int $id): void
    {
        $this->verifyCsrf($_POST);

        if ($_SESSION['user_role'] != 1) {
            http_response_code(403);
            die('Только администратор может блокировать пользователей');
        }

        $userModel = new User();
        $user = $userModel->findById($id);

        if (!$user) {
            http_response_code(404);
            die('Пользователь не найден');
        }

        $newStatus = $user['is_blocked'] ? 0 : 1;
        $userModel->update($id, ['is_blocked' => $newStatus]);

        // Логирование
        $auditModel = new \App\Models\AdminLog();
        $auditModel->log($_SESSION['user_id'], 'toggle_block', 'users', $id);

        $_SESSION['success'] = $newStatus ? 'Пользователь заблокирован' : 'Пользователь разблокирован';
        $this->redirect('/admin/users');
    }

    /**
     * Смена роли пользователя.
     */
    public function changeRole(int $id): void
    {
        $this->verifyCsrf($_POST);

        if ($_SESSION['user_role'] != 1) {
            http_response_code(403);
            die('Только администратор может менять роли');
        }

        $roleId = (int)($_POST['role_id'] ?? 0);
        if (!in_array($roleId, [1, 2, 3, 4])) {
            $_SESSION['error'] = 'Неверная роль';
            $this->redirect('/admin/users');
            return;
        }

        $userModel = new User();
        $userModel->update($id, ['role_id' => $roleId]);

        // Логирование
        $auditModel = new \App\Models\AdminLog();
        $auditModel->log($_SESSION['user_id'], 'change_role', 'users', $id);

        $_SESSION['success'] = 'Роль изменена';
        $this->redirect('/admin/users');
    }

    /**
     * Управление постами (список).
     */
    public function posts(): void
    {
        $db = \App\Core\Database::getInstance()->getConnection();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $stmt = $db->prepare("
            SELECT p.*, u.login as author_login, c.name as category_name
            FROM posts p
            JOIN users u ON p.user_id = u.id
            JOIN categories c ON p.category_id = c.id
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $total = $db->query("SELECT COUNT(*) FROM posts")->fetchColumn();
        $totalPages = ceil($total / $limit);

        $this->render('admin/posts', [
            'posts' => $posts,
            'pagination' => ['current' => $page, 'total' => $totalPages],
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Модерация комментариев.
     */
    public function comments(): void
    {
        $db = \App\Core\Database::getInstance()->getConnection();

        $status = $_GET['status'] ?? 'pending';
        
        $stmt = $db->prepare("
            SELECT c.*, u.login as user_login, p.title as post_title
            FROM comments c
            JOIN users u ON c.user_id = u.id
            JOIN posts p ON c.post_id = p.id
            WHERE c.status = :status
            ORDER BY c.created_at DESC
            LIMIT 50
        ");
        $stmt->execute(['status' => $status]);
        $comments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('admin/comments', [
            'comments' => $comments,
            'current_status' => $status,
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Одобрение/отклонение комментария.
     */
    public function moderateComment(int $id): void
    {
        $this->verifyCsrf($_POST);

        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['approved', 'rejected'])) {
            $_SESSION['error'] = 'Неверный статус';
            $this->redirect('/admin/comments');
            return;
        }

        $commentModel = new Comment();
        $commentModel->update($id, ['status' => $status]);

        // Логирование
        $auditModel = new \App\Models\AdminLog();
        $auditModel->log($_SESSION['user_id'], 'moderate_comment', 'comments', $id);

        $_SESSION['success'] = 'Комментарий ' . ($status === 'approved' ? 'одобрен' : 'отклонен');
        $this->redirect('/admin/comments');
    }

    /**
     * Жалобы (reports).
     */
    public function reports(): void
    {
        $db = \App\Core\Database::getInstance()->getConnection();

        $stmt = $db->query("
            SELECT r.*, u.login as reporter_login
            FROM reports r
            JOIN users u ON r.user_id = u.id
            ORDER BY 
                CASE r.status WHEN 'pending' THEN 0 WHEN 'reviewed' THEN 1 ELSE 2 END,
                r.created_at DESC
            LIMIT 100
        ");
        $reports = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('admin/reports', [
            'reports' => $reports,
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Обработка жалобы.
     */
    public function moderateReport(int $id): void
    {
        $this->verifyCsrf($_POST);

        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['pending', 'reviewed', 'resolved', 'ignored'])) {
            $_SESSION['error'] = 'Неверный статус';
            $this->redirect('/admin/reports');
            return;
        }

        $reportModel = new Report();
        $reportModel->update($id, ['status' => $status]);

        // Если жалоба на комментарий и она подтверждена - удаляем комментарий
        if ($status === 'resolved') {
            $db = \App\Core\Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT reportable_id FROM reports WHERE id = ?");
            $stmt->execute([$id]);
            $report = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($report && $report['reportable_type'] === 'comment') {
                $commentModel = new Comment();
                $commentModel->delete($report['reportable_id']);
            }
        }

        // Логирование
        $auditModel = new \App\Models\AdminLog();
        $auditModel->log($_SESSION['user_id'], 'moderate_report', 'reports', $id);

        $_SESSION['success'] = 'Жалоба обновлена';
        $this->redirect('/admin/reports');
    }

    /**
     * Обратная связь (список сообщений).
     */
    public function feedback(): void
    {
        $db = \App\Core\Database::getInstance()->getConnection();

        $stmt = $db->query("
            SELECT f.*, u.login as user_login
            FROM feedback f
            LEFT JOIN users u ON f.user_id = u.id
            ORDER BY f.created_at DESC
            LIMIT 100
        ");
        $feedbacks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('admin/feedback', [
            'feedbacks' => $feedbacks,
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Ответ на сообщение обратной связи.
     */
    public function replyFeedback(int $id): void
    {
        $this->verifyCsrf($_POST);

        $response = trim($_POST['admin_response'] ?? '');
        $status = $_POST['status'] ?? 'pending';

        $feedbackModel = new Feedback();
        $feedbackModel->update($id, [
            'admin_response' => $response,
            'status' => $status
        ]);

        // Логирование
        $auditModel = new \App\Models\AdminLog();
        $auditModel->log($_SESSION['user_id'], 'reply_feedback', 'feedback', $id);

        $_SESSION['success'] = 'Ответ сохранен';
        $this->redirect('/admin/feedback');
    }

    /**
     * Логи аудита.
     */
    public function logs(): void
    {
        $db = \App\Core\Database::getInstance()->getConnection();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $stmt = $db->prepare("
            SELECT al.*, u.login as admin_login
            FROM admin_logs al
            LEFT JOIN users u ON al.admin_user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $total = $db->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn();
        $totalPages = ceil($total / $limit);

        $this->render('admin/logs', [
            'logs' => $logs,
            'pagination' => ['current' => $page, 'total' => $totalPages],
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Настройки сайта.
     */
    public function settings(): void
    {
        $this->render('admin/settings', [
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Сохранение настроек.
     */
    public function saveSettings(): void
    {
        $this->verifyCsrf($_POST);

        if ($_SESSION['user_role'] != 1) {
            http_response_code(403);
            die('Только администратор может изменять настройки');
        }

        // Здесь можно реализовать сохранение настроек в БД или файл
        // Для простоты - только логирование действия
        $auditModel = new \App\Models\AdminLog();
        $auditModel->log($_SESSION['user_id'], 'update_settings', 'settings', 0);

        $_SESSION['success'] = 'Настройки сохранены';
        $this->redirect('/admin/settings');
    }
}
