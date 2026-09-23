<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Bspay;

use App\Integrations\Payments\Contracts\PaymentGateway;
use DomainException;
use RuntimeException;

final class BspayGateway implements PaymentGateway
{
    private string $baseUrl;
    private array $credentials;
    private array $settings;
    private ?string $accessToken = null;

    public function __construct(array $config)
    {
        $this->credentials = (array)($config['credentials'] ?? []);
        $this->settings = (array)($config['settings'] ?? []);
        $this->baseUrl = rtrim((string)($this->settings['base_url'] ?? 'https://api.bspay.co'), '/');
    }

    public function createDeposit(array $data): array
    {
        $payer = (array)($data['payer'] ?? []);
        $document = preg_replace('/\D+/', '', (string)($payer['document'] ?? '')) ?: '';
        $payload = [
            'amount' => round(((int)$data['amount_minor']) / 100, 2),
            'currency' => 'BRL',
            'external_id' => (string)$data['payment_id'],
        ];
        $postbackUrl = trim((string)($data['postback_url'] ?? ''));
        if ($postbackUrl !== '') {
            if (!$this->isValidPublicHttpsUrl($postbackUrl)) {
                throw new DomainException('A URL de postback da BSPAY deve ser uma URL HTTPS pública e válida.');
            }
            $payload['postback_url'] = $postbackUrl;
        }
        $payerPayload = array_filter([
            'name' => trim((string)($payer['name'] ?? '')),
            'document' => $document,
            'email' => trim((string)($payer['email'] ?? '')),
        ], static fn(mixed $v): bool => $v !== '');
        if ($payerPayload !== []) $payload['payer'] = $payerPayload;

        $response = $this->request('POST', '/v2/transactions/cashin', $payload);
        $this->assertSuccess($response, 'Falha ao gerar cobrança PIX na BSPAY.');
        $body = (array)$response['body'];
        $remote = is_array($body['data'] ?? null) ? $body['data'] : $body;
        $paymentInfo = is_array($remote['payment_info'] ?? null) ? $remote['payment_info'] : [];
        $externalId = trim((string)($remote['transaction_id'] ?? ''));
        $pixCode = trim((string)($paymentInfo['qrcode'] ?? ''));
        if ($externalId === '' || $pixCode === '') throw new DomainException('A BSPAY não retornou uma cobrança PIX válida.');

        return [
            'external_id' => $externalId,
            'status' => strtoupper((string)($remote['status'] ?? 'PENDING')),
            'payment_code' => $pixCode,
            'qr_code' => $pixCode,
            'expires_at' => $this->normalizeDate((string)($paymentInfo['expires_at'] ?? '')),
            'metadata' => [
                'provider' => 'BSPAY',
                'request_id' => $body['request_id'] ?? null,
                'fee' => $remote['fee'] ?? null,
                'payment_method' => $remote['payment_method'] ?? 'pix',
                'remote_external_id' => $remote['external_id'] ?? (string)$data['payment_id'],
            ],
        ];
    }

    public function createWithdrawal(array $data): array
    {
        $pix = (array)($data['pix'] ?? []);
        $key = trim((string)($pix['key_value'] ?? ''));
        if ($key === '') throw new DomainException('Chave PIX de saque não informada.');
        $keyType = $this->normalizePixKeyType((string)($pix['key_type'] ?? ''), $key);
        $payload = [
            'external_id' => (string)$data['payment_id'],
            'amount' => round(((int)$data['amount_minor']) / 100, 2),
            'currency' => 'BRL',
            'key' => $key,
            'description' => (string)($this->settings['withdrawal_description'] ?? 'Saque da plataforma'),
        ];
        if ($keyType !== null) $payload['key_type'] = $keyType;

        $response = $this->request('POST', '/v2/transactions/cashout', $payload, true);
        $this->assertSuccess($response, 'Falha ao solicitar saque PIX na BSPAY.');
        $body = (array)$response['body'];
        $remote = is_array($body['data'] ?? null) ? $body['data'] : $body;
        $externalId = trim((string)($remote['transaction_id'] ?? ''));
        if ($externalId === '') throw new DomainException('A BSPAY não retornou o identificador do saque.');

        return [
            'external_id' => $externalId,
            'status' => strtoupper((string)($remote['status'] ?? 'PROCESSING')),
            'metadata' => [
                'provider' => 'BSPAY',
                'request_id' => $body['request_id'] ?? null,
                'fee' => $remote['fee'] ?? null,
                'remote_external_id' => $remote['external_id'] ?? (string)$data['payment_id'],
            ],
        ];
    }

    public function getTransaction(string $externalId): array
    {
        $externalId = trim($externalId);
        if ($externalId === '') throw new DomainException('Identificador externo da BSPAY não informado.');
        $response = $this->request('POST', '/v2/account/transactions/list', ['page'=>1,'page_size'=>100]);
        $this->assertSuccess($response, 'Falha ao consultar transações na BSPAY.');
        $body = (array)$response['body'];
        $data = $body['data'] ?? [];
        $rows = [];
        if (is_array($data)) {
            if (isset($data['transactions']) && is_array($data['transactions'])) $rows = $data['transactions'];
            elseif (isset($data['items']) && is_array($data['items'])) $rows = $data['items'];
            elseif (array_is_list($data)) $rows = $data;
        }
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            if ((string)($row['transaction_id'] ?? '') !== $externalId && (string)($row['external_id'] ?? '') !== $externalId) continue;
            return [
                'external_id' => (string)($row['transaction_id'] ?? $externalId),
                'status' => strtoupper((string)($row['status'] ?? 'PROCESSING')),
                'type' => $row['type'] ?? null,
                'amount' => $row['amount'] ?? null,
                'metadata' => $row,
            ];
        }
        throw new DomainException('Transação BSPAY não localizada na consulta recente.');
    }

    public function handleWebhook(array $payload, array $headers = []): array
    {
        return $payload;
    }

    public function validateWebhook(string $rawBody, array $headers): void
    {
        if (trim($rawBody) === '') throw new DomainException('Webhook BSPAY sem conteúdo.');

        // A confirmação financeira não confia apenas no callback recebido.
        // O PaymentService reconcilia a transação com a API autenticada da BSPAY
        // antes de creditar depósito, concluir saque ou executar estorno.
        $timestamp = trim((string)($this->header($headers, 'X-Webhook-Timestamp') ?? ''));
        if ($timestamp !== '' && ctype_digit($timestamp) && abs(time() - (int)$timestamp) > 600) {
            throw new DomainException('Webhook BSPAY fora da janela de tempo permitida.');
        }
    }

    private function request(string $method, string $path, ?array $payload = null, bool $signed = false): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('Extensão cURL do PHP não está habilitada.');
        $token = $this->token();
        $headers = ['Authorization: Bearer '.$token, 'Accept: application/json'];
        $rawBody = null;
        if ($payload !== null) {
            $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
        }
        if ($signed) {
            $signingKey = trim((string)($this->credentials['signing_key'] ?? ''));
            if ($signingKey === '') throw new DomainException('Signing Key da BSPAY não configurada para saques.');
            $timestamp = (string)time();
            $nonce = $this->uuid();
            $signature = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.($rawBody ?? ''), $signingKey);
            $headers[] = 'X-Signature: '.$signature;
            $headers[] = 'X-Timestamp: '.$timestamp;
            $headers[] = 'X-Nonce: '.$nonce;
        }
        return $this->curl($method, $this->baseUrl.$path, $headers, $rawBody);
    }

    private function token(): string
    {
        if ($this->accessToken !== null) return $this->accessToken;
        $clientId = trim((string)($this->credentials['client_id'] ?? ''));
        $clientSecret = trim((string)($this->credentials['client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') throw new DomainException('Credenciais BSPAY não configuradas.');
        $body = json_encode(['grant_type'=>'client_credentials'], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $response = $this->curl('POST', $this->baseUrl.'/v2/oauth/token', [
            'Authorization: Basic '.base64_encode($clientId.':'.$clientSecret),
            'Content-Type: application/json',
            'Accept: application/json',
        ], $body);
        $this->assertSuccess($response, 'Falha ao autenticar na BSPAY.');
        $data = (array)$response['body'];
        $token = trim((string)($data['access_token'] ?? ($data['data']['access_token'] ?? '')));
        if ($token === '') throw new DomainException('A BSPAY não retornou access_token.');
        return $this->accessToken = $token;
    }

    private function curl(string $method, string $url, array $headers, ?string $rawBody): array
    {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ];
        if ($rawBody !== null) $options[CURLOPT_POSTFIELDS] = $rawBody;
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false) throw new RuntimeException('Falha de comunicação com a BSPAY: '.$error);
        $decoded = json_decode((string)$raw, true);
        return ['status'=>$status, 'body'=>is_array($decoded)?$decoded:['success'=>false,'error'=>['message'=>'Resposta inválida da BSPAY.'],'raw'=>$raw]];
    }

    private function assertSuccess(array $response, string $fallback): void
    {
        $status = (int)($response['status'] ?? 0);
        $body = (array)($response['body'] ?? []);
        if ($status >= 200 && $status < 300 && ($body['success'] ?? true) !== false) return;
        $error = is_array($body['error'] ?? null) ? $body['error'] : [];
        $message = trim((string)($error['message'] ?? $body['message'] ?? ''));
        $code = trim((string)($error['code'] ?? ''));
        throw new DomainException(($message !== '' ? $message : $fallback).($code !== '' ? ' ('.$code.')' : ''));
    }

    private function isValidPublicHttpsUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) return false;
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') return false;
        $host = strtolower(trim((string)($parts['host'] ?? '')));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) return false;
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        return str_contains($host, '.');
    }

    private function normalizePixKeyType(string $type, string $key): ?string
    {
        $type = strtolower(trim($type));
        if ($type === 'email') return 'email';
        if (in_array($type, ['phone','phonenumber','telefone','celular'], true)) return 'phone';
        if (in_array($type, ['random','randomkey','evp','aleatoria','aleatória'], true)) return 'random';
        if ($type === 'cnpj') return 'cnpj';
        if (in_array($type, ['cpf','document','documento'], true)) {
            $digits = preg_replace('/\D+/', '', $key) ?: '';
            return strlen($digits) === 14 ? 'cnpj' : (strlen($digits) === 11 ? 'cpf' : null);
        }
        return null;
    }

    private function normalizeDate(string $value): ?string
    {
        if ($value === '') return null;
        $time = strtotime($value);
        return $time === false ? null : gmdate('Y-m-d H:i:s', $time);
    }

    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string)$key, $name) !== 0) continue;
            return is_array($value) ? (string)reset($value) : (string)$value;
        }
        return null;
    }

    private function uuid(): string
    {
        $d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);$h=bin2hex($d);
        return sprintf('%s-%s-%s-%s-%s',substr($h,0,8),substr($h,8,4),substr($h,12,4),substr($h,16,4),substr($h,20));
    }
}
