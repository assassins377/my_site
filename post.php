<?php
require_once 'includes/functions.php';

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    die('Пост не найден');
}

$post = getPost((int)$id);
if (!$post) {
    die('Пост не найден');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= e($post['title']) ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1><a href="/"><?= SITE_NAME ?></a></h1>
    </header>

    <main>
        <article>
            <h1><?= e($post['title']) ?></h1>
            <div><?= nl2br(e($post['content'])) ?></div>
            <small>Опубликовано: <?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></small>
        </article>
    </main>
</body>
</html>
