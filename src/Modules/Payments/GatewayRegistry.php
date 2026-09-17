<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Integrations\Payments\Contracts\PaymentGateway;
use App\Integrations\Payments\Pixup\PixupGateway;
use App\Integrations\Payments\Sandbox\SandboxPixGateway;
use DomainException;

final class GatewayRegistry
{
    public function resolve(array $config): PaymentGateway
    {
        return match ((string) ($config['code'] ?? '')) {
            'sandbox_pix' => new SandboxPixGateway(),
            'pixup' => new PixupGateway($config),
            default => throw new DomainException('Adapter do gateway não está instalado.'),
        };
    }
}
