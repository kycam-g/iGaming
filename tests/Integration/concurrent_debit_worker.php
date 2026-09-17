<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\Wallet\WalletService;

$userId = $argv[1] ?? '';
$key = $argv[2] ?? '';
try {
    (new WalletService())->debit($userId, 'CASH', 8000, 'test', $key, $key, 'TEST_DEBIT');
    echo 'OK';
} catch (DomainException $e) {
    echo str_contains($e->getMessage(), 'Insufficient') ? 'INSUFFICIENT' : 'DOMAIN_ERROR';
}
