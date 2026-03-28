# Anime Blog Platform (PHP 8.2 + MySQL 8 + Nginx)

Полноценная платформа для блога аниме с ролевой моделью, админ-панелью и современным стеком технологий.

## 🛠 Требования к окружению

- **PHP:** 8.2+ (расширения: `pdo`, `pdo_mysql`, `mbstring`, `json`, `curl`, `gd`, `intl`)
- **MySQL:** 8.0+
- **Web-сервер:** Nginx + PHP-FPM 8.2
- **Composer:** Последняя стабильная версия
- **OS:** Linux (Ubuntu/Debian/CentOS) или macOS

---

## 🚀 Установка

### 1. Клонирование и зависимости
```bash
cd /workspace/blog
composer install --no-dev --optimize-autoloader
```

### 2. Настройка окружения
Скопируйте файл `.env.example` в `.env` и заполните данные:
```bash
cp .env.example .env
```
**Важно:** Измените пароль базы данных и данные администратора в `.env`.

### 3. Настройка прав доступа
Убедитесь, что веб-сервер имеет права на запись в следующие директории:
```bash
chown -R www-data:www-data /workspace/blog/storage
chown -R www-data:www-data /workspace/blog/logs
chown -R www-data:www-data /workspace/blog/public/uploads
chmod -R 775 /workspace/blog/storage
chmod -R 775 /workspace/blog/logs
chmod -R 775 /workspace/blog/public/uploads
```

### 4. База данных
1. Создайте базу данных в MySQL:
   ```sql
   CREATE DATABASE anime_blog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'blog_user'@'localhost' IDENTIFIED BY 'your_secure_password';
   GRANT ALL PRIVILEGES ON anime_blog.* TO 'blog_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
2. Импортируйте дамп структуры и данных:
   ```bash
   mysql -u blog_user -p anime_blog < database/dump.sql
   ```

### 5. Конфигурация Nginx
1. Скопируйте конфиг в папку сайтов Nginx:
   ```bash
   sudo cp nginx.conf /etc/nginx/sites-available/anime-blog
   sudo ln -s /etc/nginx/sites-available/anime-blog /etc/nginx/sites-enabled/
   ```
2. Отредактируйте путь к корню проекта и сокету PHP-FPM в `nginx.conf`:
   - `root /workspace/blog/public;`
   - `fastcgi_pass unix:/run/php/php8.2-fpm.sock;` (путь может отличаться)
3. Перезапустите Nginx:
   ```bash
   sudo nginx -t
   sudo systemctl restart nginx
   ```

---

## 🔑 Доступ по умолчанию

После импорта дампа доступны следующие учетные записи:

| Роль | Логин | Пароль |
|------|-------|--------|
| **Admin** | `admin` | `admin` (смените немедленно!) |
| **Moderator** | `moder` | `moder123` |
| **User** | `user` | `user123` |

---

## 📂 Структура ключевых папок

- `public/` — единственная публичная точка входа. Все запросы идут через `index.php`.
- `storage/torrents/` — торрент-файлы хранятся **вне** публичной зоны. Доступ только через контроллер `DownloadController`.
- `logs/` — логи ошибок приложения.
- `src/` — исходный код (Models, Views, Controllers, Core).

---

## 🔒 Безопасность

- **SQL Injection:** Используется PDO с подготовленными выражениями во всех запросах.
- **XSS:** Все пользовательские данные экранируются через `htmlspecialchars()` при выводе.
- **CSRF:** Все формы содержат токены защиты.
- **Пароли:** Хэшируются алгоритмом Bcrypt.
- **Загрузка файлов:** Строгая проверка MIME-типов, конвертация изображений в WebP, запрет выполнения скриптов в папке загрузок через Nginx.

---

## 🧪 Тестирование

Запустите встроенный скрипт проверки конфигурации:
```bash
php tests/check_system.php
```

---

## 📞 Поддержка

При возникновении проблем проверьте файл `logs/error.log`.
