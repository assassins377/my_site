<?php
/**
 * Страница регистрации
 */
require_once __DIR__ . '/../../Core/helpers.php';

$pageTitle = 'Регистрация - Anime Blog';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../Controllers/AuthController.php';
    $controller = new AuthController();
    $controller->register();
    exit;
}

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">
                    <i class="bi bi-person-plus"></i> Регистрация
                </h2>
                
                <form method="POST" action="/register">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="login" class="form-label">
                                <i class="bi bi-person"></i> Логин *
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="login" 
                                   name="login" 
                                   required 
                                   autofocus
                                   placeholder="Придумайте логин"
                                   minlength="3"
                                   maxlength="50">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">
                                <i class="bi bi-envelope"></i> Email *
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   required 
                                   placeholder="example@mail.com">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i> Пароль *
                        </label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   minlength="8"
                                   placeholder="Минимум 8 символов">
                            <button class="btn btn-outline-secondary" type="button" id="generatePasswordBtn" title="Сгенерировать пароль">
                                <i class="bi bi-key"></i>
                            </button>
                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn" title="Показать/скрыть">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Используйте буквы, цифры и спецсимволы для надежного пароля</div>
                        
                        <!-- Индикатор сложности пароля -->
                        <div class="progress mt-2" style="height: 5px;">
                            <div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small id="passwordStrengthText" class="form-text"></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">
                            <i class="bi bi-lock-fill"></i> Подтверждение пароля *
                        </label>
                        <input type="password" 
                               class="form-control" 
                               id="password_confirm" 
                               name="password_confirm" 
                               required
                               placeholder="Повторите пароль">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="rules" required>
                        <label class="form-check-label" for="rules">
                            Я согласен с <a href="#" target="_blank">правилами сайта</a> *
                        </label>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Зарегистрироваться
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-0">Уже есть аккаунт?</p>
                    <a href="/login" class="btn btn-outline-secondary w-100 mt-2">
                        <i class="bi bi-box-arrow-in-right"></i> Войти
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Инициализация генератора пароля
    initPasswordGenerator('password', 'generatePasswordBtn', 'togglePasswordBtn', 'passwordStrengthBar', 'passwordStrengthText');
});
</script>

<?php
$content = ob_get_clean();
$viewFile = __FILE__;
require __DIR__ . '/layouts/main.php';
?>
