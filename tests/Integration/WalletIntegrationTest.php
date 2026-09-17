<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database\Database;
use App\Modules\Wallet\WalletService;

$pdo = Database::connection();
$wallet = new WalletService();
$userId = testUuid();

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO users (id,email,username,status) VALUES (:id,:email,:username,'ACTIVE')");
    $stmt->execute([
        'id' => $userId,
        'email' => 'wallet-test-' . bin2hex(random_bytes(4)) . '@example.com',
        'username' => 'wallet_' . bin2hex(random_bytes(4)),
    ]);
    $wallet->createForUser($userId, $pdo);
    $pdo->commit();

    $first = $wallet->credit($userId, 'CASH', 10000, 'test', 'credit-1', 'wallet-test-credit-1', 'TEST_CREDIT');
    assertSameValue(10000, $first['balance_minor'], 'credit should set R$100.00');
    assertSameValue(false, $first['idempotent_replay'], 'first call is not replay');

    $replay = $wallet->credit($userId, 'CASH', 10000, 'test', 'credit-1', 'wallet-test-credit-1', 'TEST_CREDIT');
    assertSameValue($first['transaction_id'], $replay['transaction_id'], 'replay returns same transaction');
    assertSameValue(true, $replay['idempotent_replay'], 'second call is replay');

    $debit = $wallet->debit($userId, 'CASH', 8000, 'test', 'debit-1', 'wallet-test-debit-1', 'TEST_DEBIT');
    assertSameValue(2000, $debit['balance_minor'], 'debit should leave R$20.00');

    $failed = false;
    try {
        $wallet->debit($userId, 'CASH', 8000, 'test', 'debit-2', 'wallet-test-debit-2', 'TEST_DEBIT');
    } catch (DomainException) {
        $failed = true;
    }
    assertTrue($failed, 'insufficient balance must fail');

    $accounts = $wallet->balance($userId);
    $cash = array_values(array_filter($accounts, fn(array $row) => $row['type'] === 'CASH'))[0] ?? null;
    assertSameValue(2000, (int) $cash['balance_minor'], 'failed debit must rollback');

    echo "WalletIntegrationTest: OK\n";
} finally {
    $stmt = $pdo->prepare('DELETE FROM users WHERE id=:id');
    try { $stmt->execute(['id' => $userId]); } catch (Throwable) {}
}
