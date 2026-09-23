<?php

declare(strict_types=1);

namespace App\Integrations\Payments\AbilityPay;

use App\Integrations\Payments\Contracts\PaymentGateway;
use DomainException;
use RuntimeException;

final class AbilityPayGateway implements PaymentGateway
{
    private string $baseUrl;
    private array $credentials;
    private array $settings;

    public function __construct(array $config)
    {
        $this->credentials = (array)($config['credentials'] ?? []);
        $this->settings = (array)($config['settings'] ?? []);
        $this->baseUrl = rtrim((string)($this->settings['base_url'] ?? 'https://abilitypay.app/api'), '/');
    }

    public function createDeposit(array $data): array
    {
        $payer = (array)($data['payer'] ?? []);
        $document = preg_replace('/\D+/', '', (string)($payer['document'] ?? '')) ?: '';
        if (!in_array(strlen($document), [11, 14], true)) throw new DomainException('CPF/CNPJ do pagador é obrigatório para gerar PIX na AbilityPay.');

        $payload = [
            'amount' => round(((int)$data['amount_minor']) / 100, 2),
            'cpf' => $document,
            'reference_id' => (string)$data['payment_id'],
            'description' => (string)($this->settings['deposit_description'] ?? 'Depósito PIX'),
        ];
        if (!empty($payer['name'])) $payload['customer_name'] = (string)$payer['name'];
        if (!empty($payer['email'])) $payload['customer_email'] = (string)$payer['email'];
        if (!empty($payer['phone'])) $payload['customer_phone'] = preg_replace('/\D+/', '', (string)$payer['phone']);

        $response = $this->request('POST', '/integrations/pix/charges', $payload);
        $body = $response['body'];
        if ($response['status'] < 200 || $response['status'] >= 300) throw new DomainException((string)($body['message'] ?? $body['error'] ?? 'Falha ao gerar cobrança PIX na AbilityPay.'));

        // A documentação atual mostra os campos na raiz, mas algumas contas/versões
        // podem encapsular a resposta em "data". Aceitamos ambos sem enfraquecer
        // as validações obrigatórias do BR Code.
        $dataBody = is_array($body['data'] ?? null) ? $body['data'] : $body;
        $paymentInfo = is_array($dataBody['payment_info'] ?? null) ? $dataBody['payment_info'] : [];
        $externalId = trim((string)($dataBody['external_id'] ?? $dataBody['transaction_id'] ?? $dataBody['id'] ?? ''));
        $pixCode = trim((string)($dataBody['pix_code'] ?? $dataBody['pixCode'] ?? $dataBody['qrcode'] ?? $dataBody['qr_code'] ?? $paymentInfo['qrcode'] ?? $paymentInfo['qr_code'] ?? ''));
        $pixCode = preg_replace('/\s+/', '', $pixCode) ?: '';
        $provider = strtoupper(trim((string)($dataBody['provider'] ?? '')));

        if ($externalId === '') {
            throw new DomainException('A AbilityPay respondeu com sucesso, mas não retornou external_id da cobrança.');
        }
        if ($provider === 'LOCAL') {
            throw new DomainException('A AbilityPay está usando o provedor LOCAL (fallback/offline) e não gerou um PIX utilizável. Tente novamente em instantes.');
        }
        if ($pixCode === '') {
            throw new DomainException('A AbilityPay criou a cobrança, mas não retornou o pix_code.');
        }
        if (!$this->validPixCode($pixCode)) {
            throw new DomainException('A AbilityPay retornou um pix_code inválido ou de teste. Tente novamente.');
        }

        return [
            'external_id' => $externalId,
            'status' => strtoupper((string)($dataBody['status'] ?? 'PENDING')),
            'payment_code' => $pixCode,
            'qr_code' => $pixCode,
            'expires_at' => null,
            'metadata' => ['provider'=>$dataBody['provider']??null,'fee_amount'=>$dataBody['fee_amount']??null,'net_amount'=>$dataBody['net_amount']??null],
        ];
    }

    public function createWithdrawal(array $data): array
    {
        $pix = (array)($data['pix'] ?? []);
        $key = trim((string)($pix['key_value'] ?? ''));
        if ($key === '') throw new DomainException('Chave PIX de saque não informada.');

        $payload = [
            'amount' => round(((int)$data['amount_minor']) / 100, 2),
            'pix_key' => $key,
            'description' => (string)($this->settings['withdrawal_description'] ?? 'Saque da plataforma'),
        ];
        $type = $this->normalizePixKeyType((string)($pix['key_type'] ?? ''), $key);
        if ($type !== null) $payload['pix_key_type'] = $type;
        $holder = preg_replace('/\D+/', '', (string)($pix['holder_document'] ?? '')) ?: '';
        if ($holder !== '') $payload['cpf'] = $holder;

        $idempotency = 'wd_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$data['payment_id']);
        $response = $this->request('POST', '/integrations/withdrawals', $payload, ['Idempotency-Key: ' . $idempotency]);
        $body = $response['body'];
        if ($response['status'] < 200 || $response['status'] >= 300) throw new DomainException((string)($body['message'] ?? 'Falha ao solicitar saque PIX na AbilityPay.'));
        $externalId = trim((string)($body['external_id'] ?? $body['id'] ?? $body['transaction_id'] ?? ''));
        if ($externalId === '') throw new DomainException('AbilityPay não retornou o identificador do saque.');

        return [
            'external_id' => $externalId,
            'status' => strtoupper((string)($body['status'] ?? 'PROCESSING')),
            'metadata' => [
                'provider'=>$body['provider']??null,
                'fee_amount'=>$body['fee_amount']??null,
                'net_amount'=>$body['net_amount']??null,
                'available_balance'=>$body['available_balance']??null,
                'idempotency_key'=>$idempotency,
            ],
        ];
    }

    public function getTransaction(string $externalId): array
    {
        $externalId = trim($externalId);
        if ($externalId === '') throw new DomainException('Identificador externo da AbilityPay não informado.');
        $response = $this->request('GET', '/integrations/transactions/' . rawurlencode($externalId));
        $body = $response['body'];
        if ($response['status'] < 200 || $response['status'] >= 300) throw new DomainException((string)($body['message'] ?? 'Falha ao consultar transação na AbilityPay.'));
        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
        return [
            'external_id'=>$externalId,
            'status'=>strtoupper((string)($data['status'] ?? 'PENDING')),
            'type'=>$data['type'] ?? null,
            'amount'=>$data['amount'] ?? null,
            'metadata'=>$data,
        ];
    }

    public function handleWebhook(array $payload, array $headers = []): array { return $payload; }

    private function request(string $method, string $path, ?array $payload = null, array $extraHeaders = []): array
    {
        $clientId = trim((string)($this->credentials['client_id'] ?? ''));
        $secret = trim((string)($this->credentials['client_secret'] ?? ''));
        if ($clientId === '' || $secret === '') throw new DomainException('Credenciais AbilityPay não configuradas.');
        if (!function_exists('curl_init')) throw new RuntimeException('Extensão cURL do PHP não está habilitada.');

        $headers = array_merge(['X-Client-Id: '.$clientId,'X-Client-Secret: '.$secret,'Accept: application/json'], $extraHeaders);
        if ($payload !== null) $headers[] = 'Content-Type: application/json';
        $ch = curl_init($this->baseUrl . $path);
        $options = [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25];
        if ($payload !== null) $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch); $error = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($raw === false) throw new RuntimeException('Falha de comunicação com a AbilityPay: '.$error);
        $decoded = json_decode((string)$raw, true);
        return ['status'=>$status,'body'=>is_array($decoded)?$decoded:['message'=>'Resposta inválida da AbilityPay.','raw'=>$raw]];
    }

    private function normalizePixKeyType(string $type, string $key): ?string
    {
        $type = strtolower(trim($type));
        if ($type === 'email') return 'email';
        if (in_array($type,['phone','phonenumber','telefone','celular'],true)) return 'phone';
        if (in_array($type,['random','randomkey','evp','aleatoria','aleatória'],true)) return 'random';
        if ($type === 'cnpj') return 'cnpj';
        if (in_array($type,['cpf','document','documento'],true)) { $digits=preg_replace('/\D+/','',$key)?:''; return strlen($digits)===14?'cnpj':(strlen($digits)===11?'cpf':null); }
        return null;
    }

    private function validPixCode(string $code): bool
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', trim($code)) ?: '');
        if ($normalized === '' || !str_contains($normalized, 'BR.GOV.BCB.PIX')) return false;
        if (str_ends_with($normalized, '6304ABCD')) return false;
        return (bool)preg_match('/6304[0-9A-F]{4}$/', $normalized);
    }
}
