<?php
/**
 * Главная страница - список постов по категориям
 */
require_once __DIR__ . '/../../Core/helpers.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Models/Category.php';
require_once __DIR__ . '/../../Models/Post.php';

$db = Database::getInstance();
$categoryModel = new Category($db);
$postModel = new Post($db);

$categories = $categoryModel->getAllWithPosts(6); // 6 постов на категорию

$pageTitle = 'Главная - Anime Blog';
$metaDescription = 'Лучшие аниме обзоры, рейтинги и обсуждения';
$metaKeywords = 'аниме, блог, рейтинги, обзоры, новинки';

ob_start();
?>

<h1 class="mb-4"><i class="bi bi-film"></i> Популярные аниме</h1>

<?php if (empty($categories)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Посты пока не добавлены. Загляните позже!
    </div>
<?php else: ?>
    <?php foreach ($categories as $category): ?>
        <section class="mb-5">
            <div class="d-flex align-items-center mb-3">
                <i class="bi bi-folder2-open text-primary fs-4 me-2"></i>
                <h2 class="h4 mb-0"><?= htmlspecialchars($category['name']) ?></h2>
                <a href="/category/<?= htmlspecialchars($category['slug']) ?>" class="btn btn-sm btn-outline-primary ms-auto">
                    Все <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($category['posts'] as $post): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm hover-shadow">
                            <!-- Постер с lazy load -->
                            <?php if ($post['poster_image']): ?>
                                <img src="/uploads/posters/<?= htmlspecialchars($post['poster_image']) ?>" 
                                     class="card-img-top" 
                                     alt="<?= htmlspecialchars($post['title']) ?>"
                                     loading="lazy"
                                     style="height: 300px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 300px;">
                                    <i class="bi bi-image text-white fs-1"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <!-- Title с title_en для всплывающей подсказки -->
                                <h5 class="card-title" title="<?= htmlspecialchars($post['title_en']) ?>">
                                    <a href="/post/<?= htmlspecialchars($post['slug']) ?>" class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($post['title']) ?>
                                    </a>
                                </h5>
                                
                                <p class="card-text text-muted small">
                                    <?= htmlspecialchars(mb_substr(strip_tags($post['description']), 0, 100)) ?>...
                                </p>
                                
                                <!-- Рейтинг -->
                                <div class="mb-2">
                                    <?php 
                                    $avgRating = round($post['avg_rating'] ?? 0, 1);
                                    $fullStars = floor($avgRating);
                                    $hasHalf = ($avgRating - $fullStars) >= 0.5;
                                    $emptyStars = 5 - $fullStars - ($hasHalf ? 1 : 0);
                                    ?>
                                    <small class="text-warning">
                                        <?php for ($i = 0; $i < $fullStars; $i++): ?>
                                            <i class="bi bi-star-fill"></i>
                                        <?php endfor; ?>
                                        <?php if ($hasHalf): ?>
                                            <i class="bi bi-star-half"></i>
                                        <?php endif; ?>
                                        <?php for ($i = 0; $i < $emptyStars; $i++): ?>
                                            <i class="bi bi-star"></i>
                                        <?php endfor; ?>
                                        <span class="text-muted ms-1">(<?= $avgRating ?>)</span>
                                    </small>
                                </div>
                                
                                <!-- Мета информация -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i> <?= $post['year'] ?>
                                        <i class="bi bi-collection-play ms-2"></i> <?= $post['episodes'] ?> эп.
                                    </small>
                                    <a href="/post/<?= htmlspecialchars($post['slug']) ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> Обзор
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
$viewFile = __FILE__;
require __DIR__ . '/layouts/main.php';
?>
