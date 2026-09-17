<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Sandbox;

use App\Integrations\Payments\Contracts\PaymentGateway;

final class SandboxPixGateway implements PaymentGateway
{
    public function createDeposit(array $data): array
    {
        $externalId = 'sbx_' . bin2hex(random_bytes(12));
        return [
            'external_id' => $externalId,
            'status' => 'PENDING',
            'payment_code' => '00020126SANDBOX.PIX.' . strtoupper($externalId),
            'qr_code' => null,
            'expires_at' => (new \DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s.u'),
            'metadata' => ['sandbox' => true],
        ];
    }

    public function createWithdrawal(array $data): array
    {
        throw new \DomainException('Sandbox withdrawals are not implemented yet.');
    }

    public function getTransaction(string $externalId): array
    {
        return ['external_id' => $externalId, 'status' => 'PENDING'];
    }

    public function handleWebhook(array $payload, array $headers = []): array
    {
        return $payload;
    }
}
