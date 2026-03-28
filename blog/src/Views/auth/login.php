<?php
/**
 * Страница входа
 */
require_once __DIR__ . '/../../Core/helpers.php';

$pageTitle = 'Вход - Anime Blog';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../Controllers/AuthController.php';
    $controller = new AuthController();
    $controller->login();
    exit;
}

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">
                    <i class="bi bi-box-arrow-in-right"></i> Вход
                </h2>
                
                <form method="POST" action="/login">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    
                    <div class="mb-3">
                        <label for="login" class="form-label">
                            <i class="bi bi-person"></i> Логин или Email
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="login" 
                               name="login" 
                               required 
                               autofocus
                               placeholder="Введите логин или email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i> Пароль
                        </label>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               required
                               placeholder="Введите пароль">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right"></i> Войти
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-0">Нет аккаунта?</p>
                    <a href="/register" class="btn btn-outline-secondary w-100 mt-2">
                        <i class="bi bi-person-plus"></i> Зарегистрироваться
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Тестовые учетки (только для разработки!) -->
        <div class="alert alert-warning mt-3 small">
            <strong><i class="bi bi-exclamation-triangle"></i> Тестовые аккаунты:</strong><br>
            Admin: admin / admin<br>
            Moderator: moder / moder123<br>
            User: user / user123
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$viewFile = __FILE__;
require __DIR__ . '/layouts/main.php';
?>
