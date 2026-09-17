<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Security\SecretBox;
use App\Core\Support\Env;
use App\Modules\Payments\GatewayConfigRepository;
use App\Modules\Payments\GatewayRegistry;
use App\Modules\Payments\WithdrawalService;
use App\Modules\Wallet\WalletService;

Env::load(dirname(__DIR__) . '/.env');

$options = getopt('', ['minutes::', 'limit::']);
$minutes = max(1, min(1440, (int)($options['minutes'] ?? 5)));
$limit = max(1, min(200, (int)($options['limit'] ?? 50)));

$service = new WithdrawalService(
    new GatewayRegistry(),
    new GatewayConfigRepository(new SecretBox()),
    new WalletService(),
);

$result = $service->reconcileStale($minutes, $limit);

echo sprintf("Reconciliação Pixup: %d saque(s) verificado(s).\n", $result['checked']);
foreach ($result['results'] as $row) {
    if ($row['ok']) {
        echo sprintf("[OK] %s -> %s\n", $row['id'], $row['status']);
    } else {
        echo sprintf("[ERRO] %s -> %s\n", $row['id'], $row['error']);
    }
}
