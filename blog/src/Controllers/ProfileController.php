<?php

namespace App\Controllers;

use App\Models\User;
use App\Core\Auth;
use App\Core\Csrf;

/**
 * Контроллер профиля пользователя.
 * Обрабатывает просмотр, редактирование профиля и смену пароля.
 */
class ProfileController extends BaseController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Просмотр профиля.
     */
    public function show(): void
    {
        $this->requireAuth();

        $userId = $_GET['id'] ?? $_SESSION['user_id'];
        $user = $this->userModel->findById((int)$userId);

        if (!$user) {
            http_response_code(404);
            die('Пользователь не найден');
        }

        // Получаем закладки пользователя
        $bookmarks = [];
        if ($userId == $_SESSION['user_id']) {
            $bookmarkModel = new \App\Models\Bookmark();
            $bookmarks = $bookmarkModel->getUserBookmarks($userId);
        }

        $this->render('profile/show', [
            'user' => $user,
            'bookmarks' => $bookmarks,
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Страница редактирования профиля.
     */
    public function edit(): void
    {
        $this->requireAuth();

        $userId = $_SESSION['user_id'];
        $user = $this->userModel->findById($userId);

        $this->render('profile/edit', [
            'user' => $user,
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Обновление профиля.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->verifyCsrf($_POST);

        $userId = $_SESSION['user_id'];
        $login = trim($_POST['login'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // Валидация
        $errors = [];

        if (strlen($login) < 3 || strlen($login) > 50) {
            $errors[] = 'Логин должен быть от 3 до 50 символов';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email';
        }

        // Проверка уникальности (если данные изменены)
        $currentUser = $this->userModel->findById($userId);
        
        if ($login !== $currentUser['login'] && $this->userModel->findByLoginOrEmail($login)) {
            $errors[] = 'Пользователь с таким логином уже существует';
        }

        if ($email !== $currentUser['email'] && $this->userModel->findByEmail($email)) {
            $errors[] = 'Пользователь с таким email уже существует';
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $this->redirect('/profile/edit');
            return;
        }

        // Обновление
        $updated = $this->userModel->update($userId, [
            'login' => $login,
            'email' => $email
        ]);

        if ($updated) {
            $_SESSION['user_login'] = $login;
            $_SESSION['success'] = 'Профиль успешно обновлен';
            $this->redirect('/profile');
        } else {
            $_SESSION['error'] = 'Ошибка при обновлении профиля';
            $this->redirect('/profile/edit');
        }
    }

    /**
     * Страница смены пароля.
     */
    public function changePassword(): void
    {
        $this->requireAuth();
        $this->render('profile/change-password', [
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Обработка смены пароля.
     */
    public function updatePassword(): void
    {
        $this->requireAuth();
        $this->verifyCsrf($_POST);

        $userId = $_SESSION['user_id'];
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

        // Валидация
        $errors = [];

        $user = $this->userModel->findById($userId);
        if (!password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Неверный текущий пароль';
        }

        if (strlen($newPassword) < 8) {
            $errors[] = 'Новый пароль должен быть не менее 8 символов';
        }

        if ($newPassword !== $newPasswordConfirm) {
            $errors[] = 'Новые пароли не совпадают';
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $this->redirect('/profile/change-password');
            return;
        }

        // Обновление пароля
        $updated = $this->userModel->update($userId, [
            'password' => $newPassword
        ]);

        if ($updated) {
            $_SESSION['success'] = 'Пароль успешно изменен';
            $this->redirect('/profile');
        } else {
            $_SESSION['error'] = 'Ошибка при смене пароля';
            $this->redirect('/profile/change-password');
        }
    }

    /**
     * Страница подтверждения удаления аккаунта.
     */
    public function confirmDelete(): void
    {
        $this->requireAuth();
        $this->render('profile/confirm-delete', [
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Удаление аккаунта.
     */
    public function delete(): void
    {
        $this->requireAuth();
        $this->verifyCsrf($_POST);

        $userId = $_SESSION['user_id'];
        $password = $_POST['password'] ?? '';

        $user = $this->userModel->findById($userId);
        if (!password_verify($password, $user['password_hash'])) {
            $_SESSION['error'] = 'Неверный пароль';
            $this->redirect('/profile/delete/confirm');
            return;
        }

        // Удаление пользователя (каскадное удаление связанных данных через FK)
        $deleted = $this->userModel->delete($userId);

        if ($deleted) {
            // Выход из системы
            $_SESSION = [];
            session_destroy();
            
            $_SESSION['success'] = 'Аккаунт успешно удален';
            $this->redirect('/');
        } else {
            $_SESSION['error'] = 'Ошибка при удалении аккаунта';
            $this->redirect('/profile/delete/confirm');
        }
    }
}
