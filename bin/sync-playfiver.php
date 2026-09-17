<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Security\SecretBox;
use App\Core\Support\Env;
use App\Modules\Casino\PlayfiverCatalogSyncService;
use App\Modules\Casino\PlayfiverConfigService;

Env::load(dirname(__DIR__) . '/.env');

try {
    $config = new PlayfiverConfigService(new SecretBox());
    $sync = new PlayfiverCatalogSyncService($config);
    $result = $sync->sync();
    echo "Catálogo Games2API sincronizado com sucesso.\n";
    echo 'Provedores: ' . $result['providers_synced'] . "\n";
    echo 'Jogos: ' . $result['games_synced'] . "\n";
    echo 'Ignorados: ' . $result['games_skipped'] . "\n";
    echo 'Catálogo: ' . $result['catalog_url'] . "\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Falha ao sincronizar catálogo Games2API: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
