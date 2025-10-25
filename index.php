<?php
require_once 'includes/functions.php';

// Обработка поиска
$search = $_GET['search'] ?? '';
$posts = [];

if ($search) {
    $posts = searchPosts($search);
} else {
    $posts = getPosts();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1><a href="/"><?= SITE_NAME ?></a></h1>
        <nav>
            <a href="/admin">Админка</a>
        </nav>

        <!-- Форма поиска -->
        <form method="get" style="margin-top: 20px;">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Поиск по заголовкам и тексту...">
            <button type="submit">Найти</button>
        </form>
    </header>

    <main>
        <?php if ($search && empty($posts)): ?>
            <p>По запросу «<?= e($search) ?>» ничего не найдено.</p>
        <?php endif; ?>

        <?php foreach ($posts as $post): ?>
            <article>
                <h2>
                    <a href="post.php?id=<?= $post['id'] ?>">
                        <?= e($post['title']) ?>
                    </a>
                </h2>
                <p><?= e(substr($post['content'], 0, 200)) ?>...</p>
                <small>Опубликовано: <?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></small>
            </article>
        <?php endforeach; ?>
    </main>
</body>
</html>
