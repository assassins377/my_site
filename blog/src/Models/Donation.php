<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Модель донатов (Donations)
 * Страница помощи проекту, реквизиты, статистика для админки
 */
class Donation
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Создание записи о донате
     * @return int|false ID доната или false
     */
    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare("
            INSERT INTO donations 
            (user_id, amount, currency, payment_method, transaction_id, status)
            VALUES 
            (:user_id, :amount, :currency, :payment_method, :transaction_id, :status)
        ");
        
        $result = $stmt->execute([
            'user_id' => $data['user_id'] ?? null,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'RUB',
            'payment_method' => $data['payment_method'] ?? 'manual',
            'transaction_id' => $data['transaction_id'] ?? null,
            'status' => $data['status'] ?? 'pending'
        ]);

        return $result ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Получение доната по ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT d.*, u.login as user_login
            FROM donations d
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.id = :id
        ");
        $stmt->execute(['id' => $id]);
        
        $donation = $stmt->fetch(PDO::FETCH_ASSOC);
        return $donation ?: null;
    }

    /**
     * Обновление статуса доната (Admin)
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['pending', 'completed', 'cancelled', 'refunded'])) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE donations 
            SET status = :status, updated_at = NOW()
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'status' => $status,
            'id' => $id
        ]);
    }

    /**
     * Получение всех донатов для админки
     */
    public function getAll(?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $where = $status ? "WHERE d.status = :status" : "";
        
        $stmt = $this->db->prepare("
            SELECT d.*, u.login as user_login
            FROM donations d
            LEFT JOIN users u ON d.user_id = u.id
            $where
            ORDER BY d.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        
        if ($status) {
            $stmt->bindValue(':status', $status);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Подсчет общей суммы завершенных донатов
     */
    public function getTotalAmount(?string $currency = null): float
    {
        $where = "WHERE status = 'completed'";
        $params = [];

        if ($currency !== null) {
            $where .= " AND currency = :currency";
            $params['currency'] = $currency;
        }

        $stmt = $this->db->prepare("
            SELECT SUM(amount) as total 
            FROM donations 
            $where
        ");
        
        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] !== null ? (float)$result['total'] : 0.0;
    }

    /**
     * Подсчет количества донатов по статусу
     */
    public function countByStatus(?string $status = null): int
    {
        if ($status === null) {
            $stmt = $this->db->query("SELECT COUNT(*) FROM donations");
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM donations WHERE status = :status");
            $stmt->execute(['status' => $status]);
        }
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Получение последних успешных донатов для публичной страницы
     */
    public function getPublicDonations(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT d.amount, d.currency, d.created_at,
                   COALESCE(u.login, 'Аноним') as donor_name
            FROM donations d
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.status = 'completed'
            ORDER BY d.created_at DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получение статистики для дашборда админа
     */
    public function getDashboardStats(): array
    {
        // Общая сумма завершенных
        $totalAmount = $this->getTotalAmount();
        
        // Количество по статусам
        $stmt = $this->db->query("
            SELECT status, COUNT(*) as count, SUM(amount) as sum
            FROM donations
            GROUP BY status
        ");
        $byStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Последние 5 донатов
        $recent = $this->getAll(null, 5, 0);
        
        return [
            'total_amount' => $totalAmount,
            'by_status' => $byStatus,
            'recent' => $recent,
            'pending_count' => $this->countByStatus('pending'),
            'completed_count' => $this->countByStatus('completed')
        ];
    }
}
