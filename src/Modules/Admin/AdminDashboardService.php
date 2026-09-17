<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Database\Database;
use PDO;

final class AdminDashboardService
{
    public function data(): array
    {
        $pdo = Database::connection();
        return [
            'metrics' => $this->metrics($pdo),
            'daily' => [
                'deposits' => $this->daily($pdo, 'DEPOSIT'),
                'withdrawals' => $this->daily($pdo, 'WITHDRAWAL'),
            ],
            'latest' => [
                'deposits' => $this->latest($pdo, 'DEPOSIT'),
                'withdrawals' => $this->latest($pdo, 'WITHDRAWAL'),
            ],
            'generated_at' => gmdate('c'),
        ];
    }

    private function metrics(PDO $pdo): array
    {
        $usersOnline = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE revoked_at IS NULL AND expires_at > NOW(6) AND last_used_at >= DATE_SUB(NOW(6), INTERVAL 5 MINUTE)")->fetchColumn();
        $registrationsTotal = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $registrationsToday = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()')->fetchColumn();
        $registrations90d = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(6), INTERVAL 90 DAY)')->fetchColumn();
        $userBalances = (int)$pdo->query("SELECT COALESCE(SUM(balance_minor),0) FROM wallet_accounts WHERE type IN ('CASH','BONUS','AFFILIATE')")->fetchColumn();

        $depositsNoLink = $this->sumPayment($pdo, 'DEPOSIT', false);
        $depositsBloggers = $this->sumPayment($pdo, 'DEPOSIT', true);
        $withdrawalsNoLink = $this->sumPayment($pdo, 'WITHDRAWAL', false);
        $withdrawalsBloggers = $this->sumPayment($pdo, 'WITHDRAWAL', true);

        $accesses = 0;
        $topLocation = null;
        try {
            $accesses = (int)$pdo->query('SELECT COUNT(*) FROM site_visits')->fetchColumn();
            $row = $pdo->query("SELECT city,region,country,COUNT(*) AS total FROM site_visits WHERE city IS NOT NULL OR region IS NOT NULL OR country IS NOT NULL GROUP BY city,region,country ORDER BY total DESC LIMIT 1")->fetch();
            if ($row) {
                $parts = array_values(array_filter([(string)($row['city'] ?? ''), (string)($row['region'] ?? ''), (string)($row['country'] ?? '')]));
                $topLocation = $parts ? implode(', ', array_unique($parts)) : null;
            }
        } catch (\Throwable) {
            // Migration may not have been run yet; dashboard remains usable.
        }

        return [
            'users_online' => $usersOnline,
            'registrations_total' => $registrationsTotal,
            'registrations_today' => $registrationsToday,
            'registrations_90d' => $registrations90d,
            'user_balances_minor' => $userBalances,
            'profit_minor' => 0,
            'profit_available' => false,
            'deposits_no_link_minor' => $depositsNoLink,
            'deposits_bloggers_minor' => $depositsBloggers,
            'withdrawals_bloggers_minor' => $withdrawalsBloggers,
            'withdrawals_no_link_minor' => $withdrawalsNoLink,
            'accesses_total' => $accesses,
            'top_location' => $topLocation,
        ];
    }

    private function sumPayment(PDO $pdo, string $kind, bool $blogger): int
    {
        $affiliateCondition = $blogger
            ? "JSON_EXTRACT(metadata, '$.affiliate_id') IS NOT NULL"
            : "JSON_EXTRACT(metadata, '$.affiliate_id') IS NULL";
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_minor),0) FROM payment_transactions WHERE kind=:kind AND status='PAID' AND {$affiliateCondition}");
        $stmt->execute(['kind' => $kind]);
        return (int)$stmt->fetchColumn();
    }

    private function daily(PDO $pdo, string $kind): array
    {
        $stmt = $pdo->prepare(
            "SELECT DATE(created_at) AS day, COUNT(*) AS total, COALESCE(SUM(amount_minor),0) AS amount_minor
             FROM payment_transactions
             WHERE kind=:kind AND status='PAID' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        $stmt->execute(['kind' => $kind]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = ['day' => (string)$row['day'], 'total' => (int)$row['total'], 'amount_minor' => (int)$row['amount_minor']];
        }
        return $rows;
    }

    private function latest(PDO $pdo, string $kind): array
    {
        $stmt = $pdo->prepare(
            "SELECT p.id,p.amount_minor,p.created_at,p.gateway_code,u.username,u.email
             FROM payment_transactions p
             JOIN users u ON u.id=p.user_id
             WHERE p.kind=:kind AND p.status='PAID'
             ORDER BY p.created_at DESC LIMIT 5"
        );
        $stmt->execute(['kind' => $kind]);
        return array_map(static fn(array $row): array => [
            'id' => (string)$row['id'],
            'user' => (string)($row['username'] ?: $row['email']),
            'created_at' => (string)$row['created_at'],
            'amount_minor' => (int)$row['amount_minor'],
            'gateway_code' => (string)$row['gateway_code'],
        ], $stmt->fetchAll());
    }
}
