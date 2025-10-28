Таблица для админов
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- В реальном проекте храните хеши!
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
-- Администратор
INSERT INTO admins (username, password) VALUES ('admin', 'password123');

-- Статья
INSERT INTO articles (title, content) VALUES (
    'Первая статья',
    'Это текст первой статьи. Здесь может быть любой контент.'
);

Создание таблицы articles
CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
