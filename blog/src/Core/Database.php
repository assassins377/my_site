<?php
/**
 * src/Core/Database.php
 * Класс для работы с базой данных (Singleton, PDO).
 * Реализует подготовленные выражения (Prepared Statements) для защиты от SQL-инъекций.
 */

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;

    /**
     * Конструктор: подключение к БД через параметры из .env
     */
    private function __construct()
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbname = $_ENV['DB_NAME'] ?? 'anime_blog';
        $username = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASS'] ?? '';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Выбрасывать исключения при ошибках
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Возвращать массивы по умолчанию
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Использовать нативные подготовленные выражения
        ];

        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // Логирование ошибки подключения (не выводить пользователю в продакшене)
            error_log("Ошибка подключения к БД: " . $e->getMessage());
            die('Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.');
        }
    }

    /**
     * Получение экземпляра класса (Singleton)
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Получение PDO соединения
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Выполнение запроса с подготовленными выражениями (SELECT, INSERT, UPDATE, DELETE)
     * @param string $sql SQL запрос с плейсхолдерами
     * @param array $params Параметры для подстановки
     * @return \PDOStatement|null Результат выполнения
     */
    public function query(string $sql, array $params = []): ?\PDOStatement
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Ошибка выполнения запроса: " . $e->getMessage() . "\nSQL: $sql");
            return null;
        }
    }

    /**
     * Получить одну строку результата
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : null;
    }

    /**
     * Получить все строки результата
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Получить последний вставленный ID
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Начать транзакцию
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Зафиксировать транзакцию
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Откатить транзакцию
     */
    public function rollBack(): bool
    {
        return $this->connection->rollBack();
    }

    // Запрет клонирования и сериализации для Singleton
    private function __clone() {}
    public function __wakeup()
    {
        throw new \Exception("Нельзя десериализовать синглтон");
    }
}
