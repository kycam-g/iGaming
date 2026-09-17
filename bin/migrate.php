<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database\Database;
use App\Core\Support\Env;

Env::load(dirname(__DIR__) . '/.env');
$pdo = Database::connection();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations ('
    . 'migration VARCHAR(190) PRIMARY KEY, '
    . 'executed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)'
    . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }

    echo "Applying {$name}...\n";
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Could not read migration {$name}.\n");
        exit(1);
    }

    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    try {
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }

        $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
        $stmt->execute(['migration' => $name]);
        echo "Applied {$name}.\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
        exit(1);
    }
}
