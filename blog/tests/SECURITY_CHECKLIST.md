# Чек-лист безопасности и функциональности

## ✅ Требования KB (Knowledge Base) - ОБЯЗАТЕЛЬНО

### Безопасность
- [x] **PDO prepare** во всех SQL-запросах (реализовано в `src/Core/Database.php` и всех моделях)
- [x] **`htmlspecialchars()`** при любом выводе данных (во всех View файлах)
- [x] **CSRF токены** во всех формах (реализовано в `src/Core/CSRF.php`)
- [x] **`config/config.php`** существует
- [x] **`logs/error.log`** существует и writable
- [x] **`database/dump.sql`** с тестовым admin/admin
- [x] **Composer** (`vendor/`) используется (autoload + зависимости)
- [x] **Пароли:** `password_hash()` / `password_verify()` (Bcrypt)

### Функциональность
- [x] **Bootstrap Icons** в UI (подключены через CDN)
- [x] **Генератор пароля:** кнопка, JS функция, копирование, индикатор сложности
- [x] **English Title:** поле `title_en` в БД и форме, **используется в `title="text"` атрибуте карточек постов**
- [x] **Комментарии:** только авторизованные + лимит 15 мин на редактирование/удаление
- [x] **Торренты:** вне public/, скачивание через `DownloadController`
- [x] **Рейтинг:** 1-10, 1 голос от пользователя, AJAX обновление
- [x] **Закладки:** AJAX toggle, страница в профиле
- [x] **Жалобы:** пользователи создают, админ/moder видит в панели
- [x] **Audit Log:** действия админов записываются в `admin_logs`
- [x] **Обратная связь:** форма + email (PHPMailer) + Rate Limit (3/час)
- [x] **Помощь проекту:** реквизиты + копирование в буфер
- [x] **WebP конвертация** при загрузке изображений
- [x] **Lazy Load** (`loading="lazy"`) на всех `<img>`

### Архитектура
- [x] **MVC паттерн:** разделение Models, Views, Controllers
- [x] **PSR-12:** кодирование, именования, отступы
- [x] **SOLID:** принципы в моделях и контроллерах
- [x] **DRY:** переиспользование кода через хелперы и трейты
- [x] **Комментарии на русском языке**

---

## 📋 Структура файлов проекта

```
/workspace/blog/
├── nginx.conf                  # Конфиг Nginx (Gzip, безопасность, кэш)
├── .env.example                # Шаблон переменных окружения
├── composer.json               # Зависимости (dotenv, PHPMailer)
├── README.md                   # Документация
├── config/
│   └── config.php              # Подключение к БД, константы
├── database/
│   └── dump.sql                # Дамп БД (13 таблиц + данные)
├── logs/
│   └── error.log               # Лог ошибок
├── public/
│   ├── index.php               # Точка входа (роутинг)
│   ├── assets/
│   │   ├── js/
│   │   │   └── password-generator.js  # Генератор паролей
│   │   └── css/
│   │       └── custom.css      # Доп. стили
│   └── uploads/posters/        # Изображения (WebP)
├── src/
│   ├── Core/
│   │   ├── Database.php        # PDO singleton
│   │   ├── Router.php          # Маршрутизация
│   │   ├── Auth.php            # Аутентификация
│   │   ├── CSRF.php            # CSRF защита
│   │   ├── AuditLog.php        # Логирование действий
│   │   └── helpers.php         # Глобальные функции
│   ├── Controllers/
│   │   ├── AuthController.php          # Вход/выход/регистрация
│   │   ├── ProfileController.php       # Профиль пользователя
│   │   ├── PostController.php          # Просмотр постов
│   │   ├── AdminController.php         # Админ-панель
│   │   ├── CommentController.php       # Комментарии
│   │   ├── RatingController.php        # Рейтинги (AJAX)
│   │   ├── BookmarkController.php      # Закладки (AJAX)
│   │   ├── ReportController.php        # Жалобы
│   │   ├── FeedbackController.php      # Обратная связь
│   │   ├── HelpController.php          # Помощь проекту
│   │   ├── DownloadController.php      # Скачивание торрентов
│   │   └── SitemapController.php       # Sitemap/RSS
│   ├── Models/
│   │   ├── User.php            # Модель пользователя
│   │   ├── Role.php            # Роли
│   │   ├── Post.php            # Посты (Anime)
│   │   ├── Category.php        # Категории
│   │   ├── Genre.php           # Жанры
│   │   ├── Comment.php         # Комментарии
│   │   ├── Rating.php          # Рейтинги
│   │   ├── Bookmark.php        # Закладки
│   │   ├── Report.php          # Жалобы
│   │   ├── Feedback.php        # Обратная связь
│   │   └── Donation.php        # Донаты
│   └── Views/
│       ├── layouts/
│       │   ├── header.php      # Хедер (меню, поиск)
│       │   └── footer.php      # Футер
│       ├── home.php            # Главная (категории)
│       ├── auth/login.php      # Вход
│       ├── auth/register.php   # Регистрация
│       ├── profile/index.php   # Профиль
│       ├── profile/bookmarks.php # Закладки
│       ├── posts/show.php      # Страница поста
│       ├── posts/create.php    # Создание поста
│       ├── posts/edit.php      # Редактирование поста
│       ├── admin/dashboard.php # Дашборд админа
│       ├── admin/users.php     # Управление пользователями
│       ├── admin/posts.php     # Управление постами
│       ├── admin/comments.php  # Модерация комментариев
│       ├── admin/reports.php   # Жалобы
│       ├── admin/logs.php      # Audit логи
│       ├── admin/settings.php  # Настройки
│       ├── feedback/index.php  # Форма обратной связи
│       └── help/index.php      # Помощь проекту
├── storage/
│   ├── cache/                  # Файловый кэш
│   └── torrents/               # Торрент файлы (вне public!)
└── tests/
    └── check_system.php        # Скрипт проверки системы
```

---

## 🔐 Проверка безопасности (ручная)

### SQL-инъекции
✅ Все запросы используют PDO prepared statements:
```php
// Пример из Post.php
$stmt = $this->db->prepare("SELECT * FROM posts WHERE slug = ?");
$stmt->execute([$slug]);
```

### XSS атаки
✅ Весь вывод экранируется:
```php
// Пример из View
<h2><?= htmlspecialchars($post['title']) ?></h2>
<p title="<?= htmlspecialchars($post['title_en']) ?>">...</p>
```

### CSRF атаки
✅ Токены в формах:
```php
<!-- В каждой форме -->
<input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
```

### Пароли
✅ Bcrypt хэширование:
```php
// При регистрации
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// При входе
if (password_verify($password, $user['password_hash'])) { ... }
```

### Загрузка файлов
✅ Проверка MIME, размера, конвертация WebP:
```php
// В PostController
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($fileType, $allowedTypes)) { ... }
imagewebp($image, $destination, 80); // Конвертация в WebP
```

### Сессии
✅ Регенерация ID после логина:
```php
session_regenerate_id(true);
```

---

## 🎯 Функциональные тесты

### 1. Пользователи
- [x] Регистрация нового пользователя
- [x] Вход/выход
- [x] Редактирование профиля
- [x] Смена пароля (требует текущий)
- [x] Удаление аккаунта (с подтверждением)
- [x] Генерация надежного пароля (JS)

### 2. Посты (Anime)
- [x] Просмотр главной (группировка по категориям)
- [x] Просмотр страницы поста
- [x] **Атрибут title="title_en" на карточках** (всплывающая подсказка)
- [x] Создание поста (Admin/Author)
- [x] **Обязательное поле title_en для Admin**
- [x] Редактирование поста
- [x] Загрузка постера (конвертация в WebP)
- [x] Скачивание торрента (через контроллер)

### 3. Рейтинг
- [x] Оценка 1-10 звезд
- [x] Только одна оценка от пользователя
- [x] AJAX обновление без перезагрузки
- [x] Подсчет среднего рейтинга

### 4. Комментарии
- [x] Добавление комментария (только авторизованные)
- [x] Статус "ожидает модерации" по умолчанию
- [x] Редактирование своих комментариев (< 15 мин)
- [x] Удаление своих комментариев (< 15 мин)
- [x] Жалоба на комментарий

### 5. Закладки
- [x] Добавление/удаление закладки (AJAX)
- [x] Список закладок в профиле

### 6. Жалобы
- [x] Создание жалобы пользователем
- [x] Просмотр жалоб в админ-панели
- [x] Управление статусами жалоб

### 7. Обратная связь
- [x] Форма для всех пользователей
- [x] Автозаполнение для авторизованных
- [x] Rate limiting (3 сообщения в час)
- [x] Отправка email админу

### 8. Админ-панель
- [x] Доступ только Admin/Moderator
- [x] Дашборд со статистикой
- [x] Управление пользователями (блок/роль)
- [x] Управление контентом (посты RU+EN)
- [x] Модерация комментариев и жалоб
- [x] Audit логи действий
- [x] Настройки сайта

### 9. SEO
- [x] Sitemap.xml
- [x] RSS feed
- [x] Meta tags на страницах

---

## 🚀 Инструкция по запуску

1. **Установить зависимости:**
   ```bash
   cd /workspace/blog
   composer install
   ```

2. **Настроить .env:**
   ```bash
   cp .env.example .env
   # Отредактировать данные БД и админа
   ```

3. **Создать БД и импортировать дамп:**
   ```sql
   CREATE DATABASE anime_blog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   ```bash
   mysql -u root -p anime_blog < database/dump.sql
   ```

4. **Настроить права:**
   ```bash
   chmod -R 775 storage logs public/uploads
   chown -R www-data:www-data storage logs public/uploads
   ```

5. **Настроить Nginx:**
   - Скопировать `nginx.conf` в `/etc/nginx/sites-available/`
   - Создать симлинк в `/etc/nginx/sites-enabled/`
   - Отредактировать пути и сокет PHP-FPM
   - Перезапустить Nginx

6. **Проверить систему:**
   ```bash
   php tests/check_system.php
   ```

7. **Войти как админ:**
   - URL: `http://your-domain/login`
   - Логин: `admin`
   - Пароль: `admin` (сменить немедленно!)

---

## 📝 Примечания разработчика

- Все модели используют PDO prepared statements
- Все View используют `htmlspecialchars()` для вывода
- CSRF токены генерируются на сессию
- Торренты хранятся вне public/ и отдаются через контроллер
- Изображения конвертируются в WebP при загрузке
- Lazy load применяется ко всем изображениям
- Код соответствует PSR-12 с комментариями на русском
