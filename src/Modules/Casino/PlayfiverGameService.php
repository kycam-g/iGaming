<?php

declare(strict_types=1);

namespace App\Modules\Casino;

use App\Core\Database\Database;
use App\Modules\Users\UserRepository;
use App\Modules\Wallet\WalletService;
use DomainException;
use RuntimeException;

final class PlayfiverGameService
{
    public function __construct(
        private readonly PlayfiverConfigService $config,
        private readonly WalletService $wallet,
        private readonly UserRepository $users,
    ) {}

    public function launch(array $user, int $gameId): array
    {
        if (!$this->config->isEnabled()) throw new DomainException('Integração PlayFiver está desativada no painel administrativo.');
        if ($gameId <= 0) throw new DomainException('Jogo inválido.');
        if (($user['status'] ?? '') !== 'ACTIVE') throw new DomainException('Conta indisponível para jogar.');

        $stmt = Database::connection()->prepare(
            "SELECT g.id,g.external_id,g.name,g.enabled,g.api_source,g.source_original,p.code AS provider_code,p.enabled AS provider_enabled "
            . "FROM casino_games g JOIN casino_providers p ON p.id=g.provider_id WHERE g.id=:id LIMIT 1"
        );
        $stmt->execute(['id'=>$gameId]);
        $game = $stmt->fetch();
        if (!$game || !((int)$game['enabled']) || !((int)$game['provider_enabled'])) throw new DomainException('Jogo indisponível.');
        if (strtoupper((string)$game['api_source']) !== 'PLAYFIVER') throw new DomainException('Este jogo não pertence à integração PlayFiver.');

        $credentials = $this->config->credentials();
        $baseUrl = rtrim((string)$this->config->publicConfig()['base_url'], '/');
        $providerCode = strtoupper(trim((string)$game['provider_code']));
        $gameCode = trim((string)$game['external_id']);
        $userCode = trim((string)($user['public_id'] ?? ''));
        if ($userCode === '' || $providerCode === '' || $gameCode === '') throw new DomainException('Dados insuficientes para abrir o jogo.');

        $balanceMinor = $this->wallet->accountBalanceMinor((string)$user['id'], 'CASH');
        $payload = [
            'method'=>'game_launch',
            'agentToken'=>$credentials['agent_token'],
            'secretKey'=>$credentials['agent_secret'],
            'user_code'=>$userCode,
            'provider_code'=>$providerCode,
            'game_code'=>$gameCode,
            'game_original'=>$this->gameOriginal($providerCode, $game['source_original'] ?? null),
            'user_balance'=>$balanceMinor / 100,
            'lang'=>'pt',
        ];

        $errors = [];
        try {
            $response = $this->postJson($baseUrl.'/api/v2/game_launch', $payload);
            $url = $this->extractLaunchUrl($response);
            if ($url !== null) {
                return [
                    'launch_url'=>$url,
                    'provider_code'=>$providerCode,
                    'game_code'=>$gameCode,
                    'launch_mode'=>'v2',
                ];
            }
            $errors[] = $this->remoteMessage($response);
        } catch (\Throwable $error) {
            $errors[] = $error->getMessage();
        }

        // Compatibilidade observada na base PlayFiver funcional fornecida ao projeto.
        // Continua sendo a mesma API PlayFiver; não há chamada a agregadores terceiros.
        $fallbackPayload = [
            'method'=>'game_launch',
            'agent_code'=>$credentials['agent_code'],
            'agent_token'=>$credentials['agent_token'],
            'user_code'=>$userCode,
            'provider_code'=>$providerCode,
            'game_code'=>$gameCode,
            'lang'=>'pt',
        ];
        try {
            $fallbackResponse = $this->postJson($baseUrl, $fallbackPayload);
            $fallbackUrl = $this->extractLaunchUrl($fallbackResponse);
            if ($fallbackUrl !== null) {
                return [
                    'launch_url'=>$fallbackUrl,
                    'provider_code'=>$providerCode,
                    'game_code'=>$gameCode,
                    'launch_mode'=>'legacy',
                ];
            }
            $errors[] = $this->remoteMessage($fallbackResponse);
        } catch (\Throwable $error) {
            $errors[] = $error->getMessage();
        }

        $errors = array_values(array_unique(array_filter(array_map('trim', $errors))));
        $detail = $errors ? ' '.implode(' | ', array_slice($errors, 0, 2)) : '';
        throw new DomainException('PlayFiver não liberou a abertura do jogo.'.$detail);
    }

    public function handleCallback(array $payload): array
    {
        if (!$this->config->isEnabled()) throw new DomainException('PLAYFIVER_DISABLED');
        $type = trim((string)($payload['type'] ?? ''));
        if ($type === '') throw new DomainException('TYPE_REQUIRED');

        $userCode = trim((string)($payload['user_code'] ?? ''));
        if ($userCode === '') throw new DomainException('USER_CODE_REQUIRED');
        $user = $this->users->findByPublicId($userCode);
        if (!$user || ($user['status'] ?? '') !== 'ACTIVE') throw new DomainException('USER_NOT_FOUND');

        if (strtoupper($type) === 'BALANCE') {
            return ['msg'=>'','balance'=>$this->money($this->wallet->accountBalanceMinor((string)$user['id'], 'CASH'))];
        }

        if (!in_array(strtolower($type), ['bet','win','winbet'], true)) throw new DomainException('TYPE_NOT_SUPPORTED');
        if (!$this->config->authenticateCallback((string)($payload['agent_code'] ?? ''), (string)($payload['agent_secret'] ?? ''))) {
            throw new DomainException('INVALID_CREDENTIALS');
        }

        $gameType = trim((string)($payload['game_type'] ?? 'slot'));
        $event = isset($payload[$gameType]) && is_array($payload[$gameType]) ? $payload[$gameType] : [];
        if (!$event && isset($payload['slot']) && is_array($payload['slot'])) $event = $payload['slot'];

        $txnId = trim((string)($event['txn_id'] ?? $payload['txn_id'] ?? ''));
        $gameCode = trim((string)($event['game_code'] ?? $payload['game_code'] ?? ''));
        if ($txnId === '' || $gameCode === '') throw new DomainException('TRANSACTION_FIELDS_REQUIRED');

        $betMinor = $this->toMinor($event['bet'] ?? 0);
        $winMinor = $this->toMinor($event['win'] ?? 0);
        $eventType = strtoupper($type);
        if ($eventType === 'BET') $winMinor = 0;
        elseif ($eventType === 'WIN') $betMinor = 0;

        $result = $this->wallet->settleCasino(
            (string)$user['id'],
            $betMinor,
            $winMinor,
            'PLAYFIVER',
            $txnId,
            $gameCode,
            $eventType,
        );

        return ['msg'=>'','balance'=>$this->money((int)$result['balance_minor'])];
    }

    private function postJson(string $url, array $payload): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('Extensão cURL do PHP não está habilitada.');
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_TIMEOUT=>45,
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$json,
            CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json','User-Agent: MZ90-PlayFiver/1.0'],
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Falha de rede: '.$error);
        }

        $decoded = json_decode((string)$raw, true, 512, JSON_BIGINT_AS_STRING);
        if ($status < 200 || $status >= 300) {
            $remote = is_array($decoded)
                ? trim((string)($decoded['msg'] ?? $decoded['message'] ?? $decoded['error'] ?? ''))
                : '';
            $suffix = $remote !== '' ? ' - '.$remote : '';
            throw new RuntimeException('HTTP '.$status.' retornado pela PlayFiver'.$suffix.'.');
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida da PlayFiver ('.($contentType ?: 'sem Content-Type').').');
        }
        return $decoded;
    }

    private function extractLaunchUrl(array $response): ?string
    {
        $status = $response['status'] ?? null;
        $success = $status === true || $status === 1 || $status === '1' || strtolower((string)$status) === 'success';
        if (!$success) return null;
        $url = trim((string)($response['launch_url'] ?? $response['gameURL'] ?? $response['game_url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') return null;
        return $url;
    }

    private function remoteMessage(array $response): string
    {
        $message = trim((string)($response['msg'] ?? $response['message'] ?? $response['error'] ?? ''));
        return $message !== '' ? $message : 'Resposta sem URL de lançamento.';
    }

    private function gameOriginal(string $providerCode, mixed $stored): bool
    {
        if ($stored !== null && $stored !== '') return in_array(strtolower(trim((string)$stored)), ['1','true','yes','original'], true);
        if ($providerCode === 'PGSOFT') return false;
        return in_array($providerCode, ['CQ9','JDB','FC','TD','SG','ACEWIN'], true);
    }

    private function toMinor(mixed $value): int
    {
        if (!is_numeric($value)) throw new DomainException('INVALID_AMOUNT');
        $amount = (float)$value;
        if (!is_finite($amount) || $amount < 0 || $amount > 100000000) throw new DomainException('INVALID_AMOUNT');
        return (int)round($amount * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function money(int $minor): float
    {
        return round($minor / 100, 2);
    }
}
