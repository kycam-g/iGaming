<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database\Database;
use App\Modules\Wallet\WalletService;

if (!function_exists('pcntl_fork')) {
    fwrite(STDERR, "WalletConcurrencyTest: SKIPPED (pcntl unavailable)\n");
    exit(0);
}

$pdo = Database::connection();
$wallet = new WalletService();
$userId = testUuid();
$email = 'concurrency-' . bin2hex(random_bytes(4)) . '@example.com';
$username = 'conc_' . bin2hex(random_bytes(4));
$stmt = $pdo->prepare("INSERT INTO users (id,email,username,status) VALUES (:id,:email,:username,'ACTIVE')");
$stmt->execute(['id' => $userId, 'email' => $email, 'username' => $username]);
$wallet->createForUser($userId);
$wallet->credit($userId, 'CASH', 10000, 'test', 'seed', 'concurrency-seed-' . $userId, 'TEST_CREDIT');

$files = [sys_get_temp_dir() . '/igaming-child-a-' . getmypid(), sys_get_temp_dir() . '/igaming-child-b-' . getmypid()];
$pids = [];
foreach ([0, 1] as $i) {
    $pid = pcntl_fork();
    if ($pid === -1) {
        throw new RuntimeException('Unable to fork test process.');
    }
    if ($pid === 0) {
        try {
            // Force a fresh PDO connection in the child process by launching a standalone PHP process.
            $script = dirname(__DIR__, 2) . '/tests/Integration/concurrent_debit_worker.php';
            $key = 'concurrent-debit-' . $userId . '-' . $i;
            passthru(sprintf('php %s %s %s > %s', escapeshellarg($script), escapeshellarg($userId), escapeshellarg($key), escapeshellarg($files[$i])), $code);
            exit($code);
        } catch (Throwable) {
            exit(2);
        }
    }
    $pids[] = $pid;
}

foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
}

$results = array_map(fn(string $file) => trim((string) @file_get_contents($file)), $files);
$successes = count(array_filter($results, fn(string $v) => $v === 'OK'));
$failures = count(array_filter($results, fn(string $v) => $v === 'INSUFFICIENT'));
assertSameValue(1, $successes, 'exactly one concurrent debit must succeed');
assertSameValue(1, $failures, 'exactly one concurrent debit must fail');

$accounts = (new WalletService())->balance($userId);
$cash = array_values(array_filter($accounts, fn(array $row) => $row['type'] === 'CASH'))[0] ?? null;
assertSameValue(2000, (int) $cash['balance_minor'], 'final balance must be R$20.00');

echo "WalletConcurrencyTest: OK\n";
foreach ($files as $file) @unlink($file);
