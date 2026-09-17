<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$tests = [
    $root . '/tests/Integration/AuthIntegrationTest.php',
    $root . '/tests/Integration/WalletIntegrationTest.php',
    $root . '/tests/Integration/WalletConcurrencyTest.php',
];

$failed = false;
foreach ($tests as $test) {
    passthru('php ' . escapeshellarg($test), $code);
    if ($code !== 0) $failed = true;
}
exit($failed ? 1 : 0);
