<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Audit\AuditLogger;
use App\Core\Database\Database;
use App\Modules\Auth\AuthService;
use App\Modules\Users\UserRepository;
use App\Modules\Wallet\WalletService;

$pdo = Database::connection();
$users = new UserRepository();
$wallet = new WalletService();
$auth = new AuthService($users, $wallet, new AuditLogger());
$email = 'auth-' . bin2hex(random_bytes(4)) . '@example.com';
$username = 'auth_' . bin2hex(random_bytes(4));
$userId = null;

try {
    $registered = $auth->register($email, $username, 'A-very-strong-test-password-123!');
    $userId = $registered['user']['id'];
    assertTrue(is_string($registered['token']) && strlen($registered['token']) >= 64, 'registration issues a bearer token');

    $accounts = $wallet->balance($userId);
    $types = array_column($accounts, 'type');
    sort($types);
    assertSameValue(['BONUS', 'CASH'], $types, 'registration provisions CASH and BONUS atomically');

    $authenticated = $auth->authenticate($registered['token']);
    assertSameValue($userId, $authenticated['id'] ?? null, 'issued session authenticates');

    $rotated = $auth->refresh($registered['token']);
    assertTrue($rotated['token'] !== $registered['token'], 'refresh rotates token');
    assertSameValue(null, $auth->authenticate($registered['token']), 'old token is revoked after refresh');
    assertSameValue($userId, $auth->authenticate($rotated['token'])['id'] ?? null, 'new token authenticates');

    $auth->logout($rotated['token']);
    assertSameValue(null, $auth->authenticate($rotated['token']), 'logout revokes session');

    echo "AuthIntegrationTest: OK\n";
} finally {
    if ($userId) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM audit_logs WHERE actor_id=:id OR entity_id=:id')->execute(['id' => $userId]);
            $pdo->prepare('DELETE FROM user_sessions WHERE user_id=:id')->execute(['id' => $userId]);
            $pdo->prepare('DELETE FROM user_credentials WHERE user_id=:id')->execute(['id' => $userId]);
            $pdo->prepare('DELETE FROM wallet_accounts WHERE wallet_id IN (SELECT id FROM wallets WHERE user_id=:id)')->execute(['id' => $userId]);
            $pdo->prepare('DELETE FROM wallets WHERE user_id=:id')->execute(['id' => $userId]);
            $pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $userId]);
            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }
}
