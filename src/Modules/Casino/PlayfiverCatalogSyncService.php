<?php

declare(strict_types=1);

namespace App\Modules\Casino;

use App\Core\Database\Database;
use DomainException;
use PDO;
use RuntimeException;

final class PlayfiverCatalogSyncService
{
    public function __construct(private readonly PlayfiverConfigService $config) {}

    /**
     * Sincroniza provedores e jogos usando o protocolo observado na base funcional:
     * POST no catálogo com method=provider_list e method=game_list.
     * Logos, destaque, ordem e acessos definidos no MZ90 são preservados.
     */
    public function sync(): array
    {
        if (!$this->gameSourceColumnsExist()) {
            throw new DomainException('Execute as migrations para habilitar a sincronização do catálogo PlayFiver.');
        }
        if (function_exists('set_time_limit')) @set_time_limit(0);

        $credentials = $this->config->catalogCredentials();
        $catalogUrl = rtrim($credentials['catalog_url'], '/');
        $agentCode = $credentials['agent_code'];
        $agentToken = $credentials['agent_token'];

        $providerPayload = $this->postJson($catalogUrl, [
            'method' => 'provider_list',
            'agent_code' => $agentCode,
            'agent_token' => $agentToken,
        ]);
        $this->assertSuccess($providerPayload, 'provider_list');
        $providers = $this->extractList($providerPayload, ['providers','data.providers','data']);
        if (!$providers) throw new DomainException('O catálogo PlayFiver não retornou provedores.');

        $remote = [];
        $gamesFetched = 0;
        foreach ($providers as $provider) {
            if (!is_array($provider)) continue;
            $remoteCode = trim((string)($provider['code'] ?? $provider['provider_code'] ?? ''));
            if ($remoteCode === '') continue;

            $gamePayload = $this->postJson($catalogUrl, [
                'method' => 'game_list',
                'agent_code' => $agentCode,
                'agent_token' => $agentToken,
                'provider_code' => $remoteCode,
            ]);
            $this->assertSuccess($gamePayload, 'game_list/'.$remoteCode);
            $games = $this->extractList($gamePayload, ['games','data.games','data']);
            $gamesFetched += count($games);
            $remote[] = ['provider'=>$provider,'games'=>$games];
        }

        if (!$remote) throw new DomainException('Nenhum provedor válido foi retornado pelo catálogo PlayFiver.');

        $pdo = Database::connection();
        $providerCount = 0;
        $gameCount = 0;
        $skippedGames = 0;
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $pdo->beginTransaction();
        try {
            // Como todas as listas foram obtidas antes desta transação, é seguro ocultar itens remotos ausentes.
            $pdo->exec("UPDATE casino_games SET enabled=0 WHERE api_source='PLAYFIVER'");
            $pdo->exec("UPDATE casino_providers SET enabled=0 WHERE api_source='PLAYFIVER'");

            $providerUpsert = $pdo->prepare(
                "INSERT INTO casino_providers(code,name,enabled,mode,api_source)\n" .
                "VALUES(:code,:name,:enabled,'PRODUCTION','PLAYFIVER')\n" .
                "ON DUPLICATE KEY UPDATE name=VALUES(name),enabled=VALUES(enabled),mode='PRODUCTION',api_source='PLAYFIVER'"
            );
            $providerLookup = $pdo->prepare('SELECT id FROM casino_providers WHERE code=:code LIMIT 1');

            $gameUpsert = $pdo->prepare(
                "INSERT INTO casino_games(provider_id,external_id,name,category,image_url,enabled,featured,sort_order,access_count,api_source,source_type,source_distribution,source_original,last_synced_at)\n" .
                "VALUES(:provider_id,:external_id,:name,:category,:image_url,:enabled,0,100,0,'PLAYFIVER',:source_type,:source_distribution,:source_original,:last_synced_at)\n" .
                "ON DUPLICATE KEY UPDATE name=VALUES(name),category=VALUES(category),image_url=VALUES(image_url),enabled=VALUES(enabled),api_source='PLAYFIVER',source_type=VALUES(source_type),source_distribution=VALUES(source_distribution),source_original=VALUES(source_original),last_synced_at=VALUES(last_synced_at)"
            );

            foreach ($remote as $bundle) {
                $provider = $bundle['provider'];
                $remoteCode = trim((string)($provider['code'] ?? $provider['provider_code'] ?? ''));
                $code = $this->normalizeProviderCode($remoteCode);
                if ($code === '') continue;
                $name = trim((string)($provider['name'] ?? $provider['provider_name'] ?? $remoteCode));
                $name = $this->cleanProviderName($name !== '' ? $name : $remoteCode, $remoteCode);
                $providerType = trim((string)($provider['gameType'] ?? $provider['game_type'] ?? $provider['type'] ?? ''));
                $enabled = $this->remoteEnabled($provider['status'] ?? $provider['enabled'] ?? 1);

                $providerUpsert->execute(['code'=>$code,'name'=>mb_substr($name,0,120),'enabled'=>$enabled]);
                $providerLookup->execute(['code'=>$code]);
                $providerId = (int)$providerLookup->fetchColumn();
                if ($providerId <= 0) continue;
                $providerCount++;

                foreach ($bundle['games'] as $game) {
                    if (!is_array($game)) { $skippedGames++; continue; }
                    $externalId = trim((string)($game['game_code'] ?? $game['gameCode'] ?? $game['code'] ?? $game['id'] ?? ''));
                    $gameName = trim((string)($game['game_name'] ?? $game['gameName'] ?? $game['name'] ?? ''));
                    if ($externalId === '' || $gameName === '') { $skippedGames++; continue; }

                    $type = trim((string)($game['game_type'] ?? $game['gameType'] ?? $game['type'] ?? $providerType));
                    $image = trim((string)($game['banner'] ?? $game['img_url'] ?? $game['image_url'] ?? $game['image'] ?? $game['icon'] ?? ''));
                    if ($image !== '' && (!$this->validHttpsUrl($image))) $image = '';
                    $gameEnabled = $this->remoteEnabled($game['status'] ?? $game['enabled'] ?? 1);
                    $original = $this->gameOriginal($remoteCode, $game['game_original'] ?? $game['original'] ?? null);

                    $gameUpsert->execute([
                        'provider_id'=>$providerId,
                        'external_id'=>mb_substr($externalId,0,190),
                        'name'=>mb_substr($gameName,0,190),
                        'category'=>$this->mapCategory($type, $remoteCode, $gameName),
                        'image_url'=>$image !== '' ? mb_substr($image,0,500) : null,
                        'enabled'=>$gameEnabled,
                        'source_type'=>mb_substr($type !== '' ? $type : 'slot',0,60),
                        'source_distribution'=>mb_substr(trim((string)($game['distribution'] ?? '')),0,60) ?: null,
                        'source_original'=>$original ? '1' : '0',
                        'last_synced_at'=>$now,
                    ]);
                    $gameCount++;
                }
            }

            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }

        return [
            'providers_synced'=>$providerCount,
            'games_synced'=>$gameCount,
            'games_received'=>$gamesFetched,
            'games_skipped'=>$skippedGames,
            'catalog_url'=>$catalogUrl,
            'synced_at'=>$now,
        ];
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
        if ($raw === false) throw new RuntimeException('Falha de rede: '.$error);
        if (strlen((string)$raw) > 30 * 1024 * 1024) throw new RuntimeException('Resposta do catálogo excedeu o limite de 30 MB.');
        $decoded = json_decode((string)$raw, true, 512, JSON_BIGINT_AS_STRING);
        if ($status < 200 || $status >= 300) {
            $remoteMessage = '';
            if (is_array($decoded)) {
                foreach (['msg','message','error','detail'] as $key) {
                    if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                        $candidate = trim((string)$decoded[$key]);
                        if ($candidate !== '') { $remoteMessage = $candidate; break; }
                    }
                }
            }
            if ($remoteMessage !== '') {
                $remoteMessage = mb_substr(preg_replace('/[\r\n\t]+/', ' ', $remoteMessage) ?? $remoteMessage, 0, 240);
                throw new RuntimeException('HTTP '.$status.' no catálogo Games2API: '.$remoteMessage);
            }
            if ($status === 400 || $status === 401 || $status === 403) {
                throw new RuntimeException('HTTP '.$status.' no catálogo Games2API. Confira o Catalog Agent Code e o Catalog Agent Token; são credenciais próprias do catálogo e podem ser diferentes das credenciais PlayFiver.');
            }
            throw new RuntimeException('HTTP '.$status.' ao consultar o catálogo Games2API.');
        }
        if (!is_array($decoded)) throw new RuntimeException('Resposta inválida do catálogo Games2API (esperado JSON; recebido '.($contentType ?: 'conteúdo desconhecido').').');
        return $decoded;
    }

    private function assertSuccess(array $payload, string $operation): void
    {
        if (!array_key_exists('status', $payload)) return;
        $status = $payload['status'];
        $ok = $status === true || $status === 1 || $status === '1' || strtolower((string)$status) === 'success';
        if ($ok) return;
        $message = trim((string)($payload['msg'] ?? $payload['message'] ?? 'Falha sem mensagem.'));
        throw new DomainException('PlayFiver '.$operation.': '.$message);
    }

    private function extractList(array $payload, array $paths): array
    {
        foreach ($paths as $path) {
            $value = $payload;
            foreach (explode('.', $path) as $part) {
                if (!is_array($value) || !array_key_exists($part, $value)) { $value = null; break; }
                $value = $value[$part];
            }
            if (is_array($value) && array_is_list($value)) return $value;
        }
        if (array_is_list($payload)) return $payload;
        return [];
    }

    private function normalizeProviderCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/[^a-z0-9_-]+/', '_', $code) ?? '';
        return trim(substr($code, 0, 60), '_-');
    }

    private function cleanProviderName(string $name, string $remoteCode): string
    {
        if (preg_match('/[\x{1100}-\x{11FF}\x{3130}-\x{318F}\x{AC00}-\x{D7A3}]/u', $name)) {
            $name = explode('_', $remoteCode, 2)[0];
        }
        return trim($name);
    }

    private function remoteEnabled(mixed $value): int
    {
        if (is_bool($value)) return $value ? 1 : 0;
        if (is_int($value) || is_float($value)) return ((int)$value) === 1 ? 1 : 0;
        $v = strtolower(trim((string)$value));
        return in_array($v, ['1','true','active','enabled','online','on','open'], true) ? 1 : 0;
    }

    private function mapCategory(string $type, string $providerCode, string $gameName): string
    {
        $value = strtolower(trim($type.' '.$providerCode.' '.$gameName));
        if (str_contains($value, 'fish') || str_contains($value, 'fishing') || str_contains($value, 'pesc')) return 'OTHER';
        if (str_contains($value, 'sport') || str_contains($value, 'sportsbook')) return 'LIVE';
        if (str_contains($value, 'roulette') || str_contains($value, 'table') || str_contains($value, 'blackjack') || str_contains($value, 'baccarat')) return 'TABLE';
        if (str_contains($value, 'live')) return 'LIVE';
        if ($type === '1' || str_contains($value, 'slot')) return 'SLOTS';
        if ($type !== '' && $type !== '1') return 'LIVE';
        return 'SLOTS';
    }

    private function gameOriginal(string $providerCode, mixed $remote): bool
    {
        if ($remote !== null && $remote !== '') {
            if (is_bool($remote)) return $remote;
            return in_array(strtolower(trim((string)$remote)), ['1','true','yes','original'], true);
        }
        $provider = strtoupper(trim($providerCode));
        if ($provider === 'PGSOFT') return false;
        return in_array($provider, ['CQ9','JDB','FC','TD','SG','ACEWIN'], true);
    }

    private function validHttpsUrl(string $url): bool
    {
        return (bool)filter_var($url, FILTER_VALIDATE_URL) && strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private function gameSourceColumnsExist(): bool
    {
        try {
            $stmt = Database::connection()->query("SHOW COLUMNS FROM casino_games LIKE 'api_source'");
            return (bool)$stmt->fetch();
        } catch (\Throwable) { return false; }
    }
}
