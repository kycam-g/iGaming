<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Pixup;

use App\Integrations\Payments\Contracts\PaymentGateway;
use DomainException;
use RuntimeException;

final class PixupGateway implements PaymentGateway
{
    private string $baseUrl;
    private array $credentials;
    private array $settings;

    public function __construct(array $config)
    {
        $this->credentials = (array) ($config['credentials'] ?? []);
        $this->settings = (array) ($config['settings'] ?? []);
        $this->baseUrl = rtrim((string) ($this->settings['base_url'] ?? 'https://api.pixupbr.com'), '/');
    }

    public function createDeposit(array $data): array
    {
        $token = $this->accessToken();
        $payload = [
            'amount' => round(((int) $data['amount_minor']) / 100, 2),
            'currency' => (string) ($data['currency'] ?? 'BRL'),
            'external_id' => (string) $data['payment_id'],
        ];
        if (!empty($data['postback_url'])) $payload['postback_url'] = (string) $data['postback_url'];
        if (!empty($data['payer'])) $payload['payer'] = $data['payer'];

        $response = $this->request('POST', '/v2/transactions/cashin', $payload, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        $body = $response['body'];
        if ($response['status'] < 200 || $response['status'] >= 300 || empty($body['success'])) {
            $message = (string) ($body['error']['message'] ?? 'Falha ao gerar cobrança PIX na Pixup.');
            throw new DomainException($message);
        }
        $remote = (array) ($body['data'] ?? []);
        $info = (array) ($remote['payment_info'] ?? []);
        return [
            'external_id' => (string) ($remote['transaction_id'] ?? ''),
            'status' => strtoupper((string) ($remote['status'] ?? 'PENDING')),
            'payment_code' => $info['qrcode'] ?? null,
            'qr_code' => $info['qrcode'] ?? null,
            'expires_at' => $this->mysqlDate($info['expires_at'] ?? null),
            'metadata' => [
                'pixup_request_id' => $body['request_id'] ?? null,
                'fee' => $remote['fee'] ?? null,
                'payment_method' => $remote['payment_method'] ?? 'pix',
            ],
        ];
    }

    public function createWithdrawal(array $data): array
    {
        throw new DomainException('Cash-out Pixup ainda não foi habilitado nesta versão.');
    }

    public function getTransaction(string $externalId): array
    {
        throw new DomainException('Consulta de transação Pixup será adicionada junto com reconciliação.');
    }

    public function handleWebhook(array $payload, array $headers = []): array
    {
        return $payload;
    }

    public function validateWebhook(string $rawBody, array $headers): void
    {
        $secret = trim((string) ($this->credentials['webhook_secret'] ?? ''));
        $verify = filter_var($this->settings['verify_webhook_signature'] ?? false, FILTER_VALIDATE_BOOL);
        if (!$verify) return;
        if ($secret === '') throw new RuntimeException('Pixup webhook signature validation is enabled but webhook_secret is missing.');
        $signature = (string) ($this->header($headers, 'X-Webhook-Signature') ?? '');
        $timestamp = (int) ($this->header($headers, 'X-Webhook-Timestamp') ?? 0);
        if ($timestamp <= 0 || abs(time() - $timestamp) > 300) throw new DomainException('Webhook timestamp inválido.');
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if ($signature === '' || !hash_equals($expected, $signature)) throw new DomainException('Assinatura de webhook inválida.');
    }

    private function accessToken(): string
    {
        $clientId = trim((string) ($this->credentials['client_id'] ?? ''));
        $clientSecret = trim((string) ($this->credentials['client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') throw new DomainException('Credenciais Pixup não configuradas.');
        $response = $this->request('POST', '/v2/oauth/token', null, [
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
        ]);
        $token = (string) ($response['body']['access_token'] ?? '');
        if ($response['status'] < 200 || $response['status'] >= 300 || $token === '') {
            throw new DomainException((string) ($response['body']['error']['message'] ?? 'Falha ao autenticar na Pixup.'));
        }
        return $token;
    }

    private function request(string $method, string $path, ?array $payload, array $headers): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('Extensão cURL do PHP não está habilitada.');
        $ch = curl_init($this->baseUrl . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
        ];
        if ($payload !== null) $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false) throw new RuntimeException('Falha de comunicação com a Pixup: ' . $error);
        $decoded = json_decode((string) $raw, true);
        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : ['raw' => $raw]];
    }

    private function mysqlDate(mixed $value): ?string
    {
        if (!$value) return null;
        try { return (new \DateTimeImmutable((string) $value))->format('Y-m-d H:i:s.u'); }
        catch (\Throwable) { return null; }
    }

    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) if (strcasecmp((string) $key, $name) === 0) return is_array($value) ? (string) reset($value) : (string) $value;
        return null;
    }
}
