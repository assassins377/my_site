<?php

namespace App\Controllers;

/**
 * Контроллер для служебных страниц.
 * Главная, поиск, sitemap, RSS.
 */
class PageController extends BaseController
{
    /**
     * Главная страница (дублирует PostController::index для гибкости).
     */
    public function home(): void
    {
        $postController = new PostController();
        $postController->index();
    }

    /**
     * Страница поиска.
     */
    public function search(): void
    {
        $query = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $results = [];
        $total = 0;

        if (!empty($query)) {
            $postModel = new \App\Models\Post();
            $results = $postModel->search($query, $limit, $offset);
            $total = $postModel->countSearch($query);
        }

        $totalPages = $total > 0 ? ceil($total / $limit) : 1;

        $this->render('pages/search', [
            'query' => htmlspecialchars($query),
            'results' => $results,
            'pagination' => ['current' => $page, 'total' => $totalPages],
            'csrf_token' => \App\Core\Csrf::generate()
        ]);
    }

    /**
     * Sitemap XML.
     */
    public function sitemap(): void
    {
        header('Content-Type: application/xml');
        
        $db = \App\Core\Database::getInstance()->getConnection();
        
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        // Главная
        echo '<url>';
        echo '<loc>' . htmlspecialchars($_ENV['SITE_URL'] ?? 'http://localhost') . '</loc>';
        echo '<changefreq>daily</changefreq>';
        echo '<priority>1.0</priority>';
        echo '</url>';
        
        // Посты
        $stmt = $db->query("SELECT slug, updated_at FROM posts ORDER BY updated_at DESC");
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            echo '<url>';
            echo '<loc>' . htmlspecialchars($_ENV['SITE_URL'] ?? 'http://localhost') . '/post/' . htmlspecialchars($row['slug']) . '</loc>';
            echo '<lastmod>' . date('Y-m-d', strtotime($row['updated_at'])) . '</lastmod>';
            echo '<changefreq>weekly</changefreq>';
            echo '<priority>0.8</priority>';
            echo '</url>';
        }
        
        // Категории
        $stmt = $db->query("SELECT id FROM categories");
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            echo '<url>';
            echo '<loc>' . htmlspecialchars($_ENV['SITE_URL'] ?? 'http://localhost') . '/category/' . $row['id'] . '</loc>';
            echo '<changefreq>weekly</changefreq>';
            echo '<priority>0.6</priority>';
            echo '</url>';
        }
        
        echo '</urlset>';
        exit;
    }

    /**
     * RSS Feed.
     */
    public function rss(): void
    {
        header('Content-Type: application/rss+xml; charset=utf-8');
        
        $db = \App\Core\Database::getInstance()->getConnection();
        
        $siteName = $_ENV['SITE_NAME'] ?? 'Anime Blog';
        $siteUrl = $_ENV['SITE_URL'] ?? 'http://localhost';
        
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">';
        echo '<channel>';
        echo '<title>' . htmlspecialchars($siteName) . '</title>';
        echo '<link>' . htmlspecialchars($siteUrl) . '</link>';
        echo '<description>Лента новостей сайта ' . htmlspecialchars($siteName) . '</description>';
        echo '<atom:link href="' . htmlspecialchars($siteUrl) . '/feed.xml" rel="self" type="application/rss+xml"/>';
        
        // Последние 20 постов
        $stmt = $db->query("
            SELECT p.title, p.description, p.slug, p.created_at, u.login as author
            FROM posts p
            JOIN users u ON p.user_id = u.id
            ORDER BY p.created_at DESC
            LIMIT 20
        ");
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            echo '<item>';
            echo '<title>' . htmlspecialchars($row['title']) . '</title>';
            echo '<link>' . htmlspecialchars($siteUrl) . '/post/' . htmlspecialchars($row['slug']) . '</link>';
            echo '<guid>' . htmlspecialchars($siteUrl) . '/post/' . htmlspecialchars($row['slug']) . '</guid>';
            echo '<description>' . htmlspecialchars($row['description']) . '</description>';
            echo '<author>' . htmlspecialchars($row['author']) . '</author>';
            echo '<pubDate>' . date('r', strtotime($row['created_at'])) . '</pubDate>';
            echo '</item>';
        }
        
        echo '</channel>';
        echo '</rss>';
        exit;
    }

    /**
     * Страница "О проекте".
     */
    public function about(): void
    {
        $this->render('pages/about', [
            'csrf_token' => \App\Core\Csrf::generate()
        ]);
    }

    /**
     * Страница правил.
     */
    public function rules(): void
    {
        $this->render('pages/rules', [
            'csrf_token' => \App\Core\Csrf::generate()
        ]);
    }
}
