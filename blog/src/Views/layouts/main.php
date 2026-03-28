<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Anime Blog') ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/assets/css/custom.css" rel="stylesheet">
    
    <meta name="description" content="<?= htmlspecialchars($metaDescription ?? 'Блог аниме - обзоры, рейтинги, обсуждения') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords ?? 'аниме, блог, рейтинги, обзоры') ?>">
</head>
<body>
    <!-- Навигация -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-film"></i> Anime Blog
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Поиск -->
                <form class="d-flex mx-auto" action="/search" method="GET" style="max-width: 400px; width: 100%;">
                    <div class="input-group">
                        <input class="form-control" type="search" name="q" placeholder="Поиск аниме..." aria-label="Search" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                        <button class="btn btn-outline-light" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
                
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/"><i class="bi bi-house"></i> Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/feedback"><i class="bi bi-envelope"></i> Обратная связь</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/help"><i class="bi bi-heart"></i> Помощь проекту</a>
                    </li>
                    
                    <?php if (isAuth()): ?>
                        <?php $user = getCurrentUser(); ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['login']) ?>
                                <?php if ($user['role_id'] == 1): ?>
                                    <span class="badge bg-danger">Admin</span>
                                <?php elseif ($user['role_id'] == 2): ?>
                                    <span class="badge bg-warning">Moderator</span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/profile"><i class="bi bi-person"></i> Профиль</a></li>
                                <li><a class="dropdown-item" href="/profile/bookmarks"><i class="bi bi-bookmark"></i> Закладки</a></li>
                                <?php if ($user['role_id'] <= 2): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/admin"><i class="bi bi-speedometer2"></i> Админ-панель</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/logout"><i class="bi bi-box-arrow-right"></i> Выход</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login"><i class="bi bi-box-arrow-in-right"></i> Вход</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/register"><i class="bi bi-person-plus"></i> Регистрация</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Основной контент -->
    <main class="container my-4">
        <!-- Уведомления -->
        <?php if (session_start() && isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Контент страницы -->
        <?php require $viewFile; ?>
    </main>

    <!-- Футер -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="bi bi-film"></i> Anime Blog</h5>
                    <p class="text-muted">Лучший блог об аниме с рейтингами, обзорами и обсуждениями.</p>
                </div>
                <div class="col-md-4">
                    <h5>Навигация</h5>
                    <ul class="list-unstyled">
                        <li><a href="/" class="text-muted text-decoration-none">Главная</a></li>
                        <li><a href="/feedback" class="text-muted text-decoration-none">Обратная связь</a></li>
                        <li><a href="/help" class="text-muted text-decoration-none">Помощь проекту</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Контакты</h5>
                    <p class="text-muted">
                        <i class="bi bi-envelope"></i> admin@animeblog.local<br>
                        <i class="bi bi-clock"></i> Поддержка 24/7
                    </p>
                </div>
            </div>
            <hr class="border-secondary">
            <div class="text-center text-muted">
                <small>&copy; <?= date('Y') ?> Anime Blog. Все права защищены.</small>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Password Generator -->
    <script src="/assets/js/password-generator.js"></script>
    <!-- Custom JS -->
    <script src="/assets/js/main.js"></script>
</body>
</html>
