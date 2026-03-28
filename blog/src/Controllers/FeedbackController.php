<?php

namespace App\Controllers;

use App\Models\Feedback;
use App\Core\Csrf;

/**
 * Контроллер обратной связи.
 * Обрабатывает форму связи с администрацией.
 */
class FeedbackController extends BaseController
{
    private Feedback $feedbackModel;

    public function __construct()
    {
        $this->feedbackModel = new Feedback();
    }

    /**
     * Страница обратной связи.
     */
    public function show(): void
    {
        $csrfToken = Csrf::generate();
        
        // Автозаполнение для авторизованных пользователей
        $prefilledData = [];
        if (isset($_SESSION['user_id'])) {
            $userModel = new \App\Models\User();
            $user = $userModel->findById($_SESSION['user_id']);
            if ($user) {
                $prefilledData = [
                    'name' => $user['login'],
                    'email' => $user['email']
                ];
            }
        }

        $this->render('feedback/show', [
            'csrf_token' => $csrfToken,
            'prefilledData' => $prefilledData
        ]);
    }

    /**
     * Обработка формы обратной связи.
     */
    public function submit(): void
    {
        $this->verifyCsrf($_POST);

        // Rate limiting: макс 3 сообщения в час с IP
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!$this->checkRateLimit($ipAddress)) {
            $_SESSION['error'] = 'Слишком много запросов. Попробуйте через час.';
            $this->redirect('/feedback');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // Валидация
        $errors = [];

        if (empty($name) || strlen($name) > 100) {
            $errors[] = 'Имя обязательно (макс. 100 символов)';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email';
        }

        if (empty($subject) || strlen($subject) > 200) {
            $errors[] = 'Тема обязательна (макс. 200 символов)';
        }

        if (empty($message) || strlen($message) < 10 || strlen($message) > 5000) {
            $errors[] = 'Сообщение должно быть от 10 до 5000 символов';
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $_SESSION['old_input'] = $_POST;
            $this->redirect('/feedback');
            return;
        }

        $userId = $_SESSION['user_id'] ?? null;

        $feedbackId = $this->feedbackModel->create([
            'user_id' => $userId,
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
            'status' => 'pending'
        ]);

        if ($feedbackId) {
            // Отправка уведомления админу через PHPMailer
            $this->sendAdminNotification($name, $email, $subject, $message);

            $_SESSION['success'] = 'Ваше сообщение отправлено. Мы ответим в ближайшее время.';
            $this->redirect('/feedback');
        } else {
            $_SESSION['error'] = 'Ошибка при отправке сообщения. Попробуйте позже.';
            $this->redirect('/feedback');
        }
    }

    /**
     * Проверка rate limiting (3 сообщения в час с IP).
     */
    private function checkRateLimit(string $ipAddress): bool
    {
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $count = $this->feedbackModel->countByIpAndTime($ipAddress, $oneHourAgo);
        
        return $count < 3;
    }

    /**
     * Отправка уведомления администратору.
     */
    private function sendAdminNotification(string $name, string $email, string $subject, string $message): void
    {
        // Получение admin email из .env
        $adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com';
        $siteName = $_ENV['SITE_NAME'] ?? 'Anime Blog';

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Настройки SMTP
            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'] ?? 'localhost';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USERNAME'] ?? '';
            $mail->Password = $_ENV['SMTP_PASSWORD'] ?? '';
            $mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'] ?? 'tls';
            $mail->Port = (int)($_ENV['SMTP_PORT'] ?? 587);

            $mail->setFrom($email, $name);
            $mail->addAddress($adminEmail, 'Administrator');
            $mail->Subject = "[$siteName] Новое сообщение: $subject";
            $mail->Body = "От: $name ($email)\n\n$message";
            $mail->isHTML(false);

            $mail->send();
        } catch (\Exception $e) {
            // Логирование ошибки отправки
            error_log("Feedback email error: " . $e->getMessage());
            // Не прерываем процесс, сообщение все равно сохранено в БД
        }
    }
}
