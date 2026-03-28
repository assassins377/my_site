<?php

namespace App\Controllers;

use App\Models\Post;
use App\Models\Category;
use App\Models\Genre;
use App\Core\Auth;
use App\Core\Csrf;

/**
 * Контроллер постов (Anime).
 * Обрабатывает просмотр, создание, редактирование и удаление постов.
 */
class PostController extends BaseController
{
    private Post $postModel;
    private Category $categoryModel;
    private Genre $genreModel;

    public function __construct()
    {
        $this->postModel = new Post();
        $this->categoryModel = new Category();
        $this->genreModel = new Genre();
    }

    /**
     * Главная страница - список постов по категориям.
     */
    public function index(): void
    {
        $categories = $this->categoryModel->getAll();
        $postsByCategory = [];

        foreach ($categories as $category) {
            $posts = $this->postModel->getByCategory($category['id'], 6);
            if (!empty($posts)) {
                $postsByCategory[$category['id']] = [
                    'category' => $category,
                    'posts' => $posts
                ];
            }
        }

        $this->render('posts/index', [
            'postsByCategory' => $postsByCategory,
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Просмотр отдельного поста.
     */
    public function show(string $slug): void
    {
        $post = $this->postModel->findBySlug($slug);

        if (!$post) {
            http_response_code(404);
            die('Пост не найден');
        }

        // Увеличение счетчика просмотров
        $this->postModel->incrementViews($post['id']);

        // Получение данных для отображения
        $category = $this->categoryModel->findById($post['category_id']);
        $genres = $this->postModel->getPostGenres($post['id']);
        
        // Рейтинг
        $ratingModel = new \App\Models\Rating();
        $averageRating = $ratingModel->getAverageRating($post['id']);
        $userRating = null;
        
        if (Auth::check()) {
            $userRating = $ratingModel->getUserRating($post['id'], $_SESSION['user_id']);
        }

        // Комментарии
        $commentModel = new \App\Models\Comment();
        $comments = $commentModel->getPostComments($post['id']);

        // Закладка
        $bookmarkModel = new \App\Models\Bookmark();
        $isBookmarked = false;
        if (Auth::check()) {
            $isBookmarked = $bookmarkModel->isBookmarked($post['id'], $_SESSION['user_id']);
        }

        $this->render('posts/show', [
            'post' => $post,
            'category' => $category,
            'genres' => $genres,
            'averageRating' => $averageRating,
            'userRating' => $userRating,
            'comments' => $comments,
            'isBookmarked' => $isBookmarked,
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Страница создания поста (Admin/Author).
     */
    public function create(): void
    {
        $this->requireRole([1, 3]); // Admin или Author

        $categories = $this->categoryModel->getAll();
        $genres = $this->genreModel->getAll();

        $this->render('posts/create', [
            'categories' => $categories,
            'genres' => $genres,
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Обработка создания поста.
     */
    public function store(): void
    {
        $this->requireRole([1, 3]);
        $this->verifyCsrf($_POST);

        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'];

        // Валидация данных
        $errors = [];

        $title = trim($_POST['title'] ?? '');
        $titleEn = trim($_POST['title_en'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $content = $_POST['content'] ?? '';
        $year = (int)($_POST['year'] ?? 0);
        $episodes = (int)($_POST['episodes'] ?? 0);
        $status = $_POST['status'] ?? '';
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $genreIds = $_POST['genre_ids'] ?? [];

        // Обязательные поля
        if (empty($title)) {
            $errors[] = 'Название (русский) обязательно';
        }

        // title_en обязательно только для Admin
        if ($userRole == 1 && empty($titleEn)) {
            $errors[] = 'Название (английский) обязательно для администратора';
        }

        if (empty($description)) {
            $errors[] = 'Описание обязательно';
        }

        if (empty($content)) {
            $errors[] = 'Контент обязателен';
        }

        if ($categoryId <= 0) {
            $errors[] = 'Категория обязательна';
        }

        // Загрузка постера
        $posterPath = null;
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
            $posterPath = $this->uploadPoster($_FILES['poster']);
            if (!$posterPath) {
                $errors[] = 'Ошибка загрузки постера';
            }
        }

        // Загрузка торрента
        $torrentPath = null;
        if (isset($_FILES['torrent']) && $_FILES['torrent']['error'] === UPLOAD_ERR_OK) {
            $torrentPath = $this->uploadTorrent($_FILES['torrent']);
            if (!$torrentPath) {
                $errors[] = 'Ошибка загрузки торрента';
            }
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $_SESSION['old_input'] = $_POST;
            $this->redirect('/posts/create');
            return;
        }

        // Создание slug
        $slug = $this->generateSlug($title);

        // Создание поста
        $postId = $this->postModel->create([
            'title' => $title,
            'title_en' => $titleEn ?: null,
            'slug' => $slug,
            'description' => $description,
            'content' => $content,
            'year' => $year ?: null,
            'episodes' => $episodes ?: null,
            'status' => $status ?: null,
            'poster_image' => $posterPath,
            'user_id' => $userId,
            'category_id' => $categoryId,
            'torrent_file' => $torrentPath
        ]);

        if ($postId) {
            // Привязка жанров
            if (!empty($genreIds)) {
                $this->postModel->attachGenres($postId, $genreIds);
            }

            // Логирование действия админа
            if ($userRole == 1) {
                $auditModel = new \App\Models\AdminLog();
                $auditModel->log($userId, 'create', 'posts', $postId);
            }

            $_SESSION['success'] = 'Пост успешно создан';
            $this->redirect('/post/' . $slug);
        } else {
            $_SESSION['error'] = 'Ошибка при создании поста';
            $this->redirect('/posts/create');
        }
    }

    /**
     * Страница редактирования поста.
     */
    public function edit(int $id): void
    {
        $this->requireRole([1, 3]);

        $post = $this->postModel->findById($id);
        if (!$post) {
            http_response_code(404);
            die('Пост не найден');
        }

        // Проверка прав: Author может редактировать только свои посты
        if ($_SESSION['user_role'] == 3 && $post['user_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            die('Доступ запрещен: вы можете редактировать только свои посты');
        }

        $categories = $this->categoryModel->getAll();
        $genres = $this->genreModel->getAll();
        $postGenres = $this->postModel->getPostGenres($id);

        $this->render('posts/edit', [
            'post' => $post,
            'categories' => $categories,
            'genres' => $genres,
            'postGenres' => $postGenres,
            'csrf_token' => Csrf::generate()
        ]);
    }

    /**
     * Обработка обновления поста.
     */
    public function update(int $id): void
    {
        $this->requireRole([1, 3]);
        $this->verifyCsrf($_POST);

        $post = $this->postModel->findById($id);
        if (!$post) {
            http_response_code(404);
            die('Пост не найден');
        }

        // Проверка прав
        if ($_SESSION['user_role'] == 3 && $post['user_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            die('Доступ запрещен');
        }

        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'];

        // Валидация
        $errors = [];

        $title = trim($_POST['title'] ?? '');
        $titleEn = trim($_POST['title_en'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $content = $_POST['content'] ?? '';
        $year = (int)($_POST['year'] ?? 0);
        $episodes = (int)($_POST['episodes'] ?? 0);
        $status = $_POST['status'] ?? '';
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $genreIds = $_POST['genre_ids'] ?? [];

        if (empty($title)) {
            $errors[] = 'Название (русский) обязательно';
        }

        if ($userRole == 1 && empty($titleEn)) {
            $errors[] = 'Название (английский) обязательно для администратора';
        }

        if (empty($description)) {
            $errors[] = 'Описание обязательно';
        }

        if (empty($content)) {
            $errors[] = 'Контент обязателен';
        }

        if ($categoryId <= 0) {
            $errors[] = 'Категория обязательна';
        }

        // Загрузка нового постера (если есть)
        $posterPath = $post['poster_image'];
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
            // Удаление старого постера
            if ($posterPath && file_exists(__DIR__ . '/../../public/' . $posterPath)) {
                unlink(__DIR__ . '/../../public/' . $posterPath);
            }
            
            $posterPath = $this->uploadPoster($_FILES['poster']);
            if (!$posterPath) {
                $errors[] = 'Ошибка загрузки постера';
            }
        }

        // Загрузка нового торрента (если есть)
        $torrentPath = $post['torrent_file'];
        if (isset($_FILES['torrent']) && $_FILES['torrent']['error'] === UPLOAD_ERR_OK) {
            // Удаление старого торрента
            if ($torrentPath && file_exists(__DIR__ . '/../../storage/' . $torrentPath)) {
                unlink(__DIR__ . '/../../storage/' . $torrentPath);
            }
            
            $torrentPath = $this->uploadTorrent($_FILES['torrent']);
            if (!$torrentPath) {
                $errors[] = 'Ошибка загрузки торрента';
            }
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $this->redirect('/posts/' . $id . '/edit');
            return;
        }

        // Обновление slug если изменилось название
        $slug = $post['slug'];
        if ($title !== $post['title']) {
            $slug = $this->generateSlug($title);
        }

        $updated = $this->postModel->update($id, [
            'title' => $title,
            'title_en' => $titleEn ?: null,
            'slug' => $slug,
            'description' => $description,
            'content' => $content,
            'year' => $year ?: null,
            'episodes' => $episodes ?: null,
            'status' => $status ?: null,
            'poster_image' => $posterPath,
            'category_id' => $categoryId,
            'torrent_file' => $torrentPath
        ]);

        if ($updated) {
            // Обновление жанров
            $this->postModel->detachGenres($id);
            if (!empty($genreIds)) {
                $this->postModel->attachGenres($id, $genreIds);
            }

            // Логирование
            if ($userRole == 1) {
                $auditModel = new \App\Models\AdminLog();
                $auditModel->log($userId, 'update', 'posts', $id);
            }

            $_SESSION['success'] = 'Пост успешно обновлен';
            $this->redirect('/post/' . $slug);
        } else {
            $_SESSION['error'] = 'Ошибка при обновлении поста';
            $this->redirect('/posts/' . $id . '/edit');
        }
    }

    /**
     * Удаление поста.
     */
    public function delete(int $id): void
    {
        $this->requireRole([1, 3]);
        $this->verifyCsrf($_POST);

        $post = $this->postModel->findById($id);
        if (!$post) {
            http_response_code(404);
            die('Пост не найден');
        }

        // Проверка прав
        if ($_SESSION['user_role'] == 3 && $post['user_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            die('Доступ запрещен');
        }

        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'];

        // Удаление файлов
        if ($post['poster_image'] && file_exists(__DIR__ . '/../../public/' . $post['poster_image'])) {
            unlink(__DIR__ . '/../../public/' . $post['poster_image']);
        }

        if ($post['torrent_file'] && file_exists(__DIR__ . '/../../storage/' . $post['torrent_file'])) {
            unlink(__DIR__ . '/../../storage/' . $post['torrent_file']);
        }

        $deleted = $this->postModel->delete($id);

        if ($deleted) {
            // Логирование
            if ($userRole == 1) {
                $auditModel = new \App\Models\AdminLog();
                $auditModel->log($userId, 'delete', 'posts', $id);
            }

            $_SESSION['success'] = 'Пост успешно удален';
            $this->redirect('/');
        } else {
            $_SESSION['error'] = 'Ошибка при удалении поста';
            $this->redirect('/post/' . $post['slug']);
        }
    }

    /**
     * Загрузка и конвертация постера в WebP.
     */
    private function uploadPoster(array $file): ?string
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            return null;
        }

        if ($file['size'] > $maxSize) {
            return null;
        }

        $uploadDir = __DIR__ . '/../../public/uploads/posters/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Генерация уникального имени
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time();
        $webpFilename = $filename . '.webp';
        $targetPath = $uploadDir . $webpFilename;

        // Загрузка и конвертация в WebP
        $sourceImage = null;
        switch ($file['type']) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($file['tmp_name']);
                break;
        }

        if ($sourceImage) {
            imagewebp($sourceImage, $targetPath, 85);
            imagedestroy($sourceImage);
            return 'uploads/posters/' . $webpFilename;
        }

        return null;
    }

    /**
     * Загрузка торрент-файла.
     */
    private function uploadTorrent(array $file): ?string
    {
        $allowedTypes = ['application/x-bittorrent', 'application/octet-stream'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if (!in_array($file['type'], $allowedTypes) && !str_ends_with(strtolower($file['name']), '.torrent')) {
            return null;
        }

        if ($file['size'] > $maxSize) {
            return null;
        }

        $uploadDir = __DIR__ . '/../../storage/torrents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = uniqid() . '_' . time() . '.torrent';
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return 'torrents/' . $filename;
        }

        return null;
    }

    /**
     * Генерация slug из названия.
     */
    private function generateSlug(string $title): string
    {
        $slug = mb_strtolower(trim($title));
        $slug = preg_replace('/[^a-zа-яё0-9\s-]/u', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Проверка уникальности
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->postModel->findBySlug($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
}
