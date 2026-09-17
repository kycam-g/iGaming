<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Support\Env;


$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    $contents = file_get_contents($envFile) ?: '';
    if (preg_match('/^APP_KEY=(.*)$/m', $contents, $m)) {
        $currentKey = trim($m[1], "\"' \t\r\n");
        if (strlen($currentKey) < 24 || str_contains($currentKey, 'change-me')) {
            $newKey = bin2hex(random_bytes(32));
            $contents = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $newKey, $contents, 1) ?? $contents;
            file_put_contents($envFile, $contents);
            echo "Generated a secure APP_KEY.\n";
        }
    }
}

Env::load(dirname(__DIR__) . '/.env');
$host = Env::get('DB_HOST', '127.0.0.1');
$port = Env::get('DB_PORT', '3306');
$name = Env::get('DB_NAME', 'igaming');
$user = Env::get('DB_USER', 'root');
$pass = Env::get('DB_PASS', '');
$charset = Env::get('DB_CHARSET', 'utf8mb4');

if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $name)) {
    fwrite(STDERR, "Invalid DB_NAME. Use only letters, numbers and underscore.\n");
    exit(1);
}

$pdo = new PDO("mysql:host={$host};port={$port};charset={$charset}", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Database {$name} is ready.\n";

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/migrate.php');
passthru($command, $exitCode);
exit($exitCode);
