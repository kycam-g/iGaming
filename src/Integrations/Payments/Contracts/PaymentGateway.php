<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Contracts;

interface PaymentGateway
{
    public function createDeposit(array $data): array;
    public function createWithdrawal(array $data): array;
    public function getTransaction(string $externalId): array;
    public function handleWebhook(array $payload, array $headers = []): array;
}
