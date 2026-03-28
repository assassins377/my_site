<?php

namespace App\Controllers;

use App\Models\User;
use App\Core\Auth;
use App\Core\Csrf;

/**
 * Контроллер аутентификации.
 * Обрабатывает вход, регистрацию и выход пользователей.
 */
class AuthController extends BaseController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Страница входа.
     */
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
            return;
        }
        
        $csrfToken = Csrf::generate();
        $this->render('auth/login', ['csrf_token' => $csrfToken]);
    }

    /**
     * Обработка формы входа.
     */
    public function login(): void
    {
        $this->verifyCsrf($_POST);

        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login) || empty($password)) {
            $_SESSION['error'] = 'Введите логин и пароль';
            $this->redirect('/login');
            return;
        }

        $user = $this->userModel->findByLoginOrEmail($login);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['error'] = 'Неверный логин или пароль';
            $this->redirect('/login');
            return;
        }

        if ($user['is_blocked']) {
            $_SESSION['error'] = 'Аккаунт заблокирован. Обратитесь к администратору.';
            $this->redirect('/login');
            return;
        }

        // Регенерация ID сессии для безопасности
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role_id'];
        $_SESSION['user_login'] = $user['login'];

        $_SESSION['success'] = 'Добро пожаловать, ' . htmlspecialchars($user['login']) . '!';
        $this->redirect('/');
    }

    /**
     * Страница регистрации.
     */
    public function showRegister(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
            return;
        }
        
        $csrfToken = Csrf::generate();
        $this->render('auth/register', ['csrf_token' => $csrfToken]);
    }

    /**
     * Обработка формы регистрации.
     */
    public function register(): void
    {
        $this->verifyCsrf($_POST);

        $login = trim($_POST['login'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // Валидация
        $errors = [];

        if (strlen($login) < 3 || strlen($login) > 50) {
            $errors[] = 'Логин должен быть от 3 до 50 символов';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Пароль должен быть не менее 8 символов';
        }

        if ($password !== $passwordConfirm) {
            $errors[] = 'Пароли не совпадают';
        }

        if ($this->userModel->findByLoginOrEmail($login)) {
            $errors[] = 'Пользователь с таким логином уже существует';
        }

        if ($this->userModel->findByEmail($email)) {
            $errors[] = 'Пользователь с таким email уже существует';
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $_SESSION['old_input'] = $_POST;
            $this->redirect('/register');
            return;
        }

        // Создание пользователя (роль Reader = 4 по умолчанию)
        $userId = $this->userModel->create([
            'login' => $login,
            'email' => $email,
            'password' => $password,
            'role_id' => 4
        ]);

        if ($userId) {
            $_SESSION['success'] = 'Регистрация успешна! Теперь вы можете войти.';
            $this->redirect('/login');
        } else {
            $_SESSION['error'] = 'Ошибка при регистрации. Попробуйте позже.';
            $this->redirect('/register');
        }
    }

    /**
     * Выход из системы.
     */
    public function logout(): void
    {
        // Уничтожение сессии
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
        
        $this->redirect('/');
    }
}
