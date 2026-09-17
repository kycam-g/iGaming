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
     * Sincroniza o catálogo público da PlayFiver.
     * Logos locais dos provedores nunca são sobrescritas.
     */
    public function sync(): array
    {
        if (!$this->gameSourceColumnsExist()) {
            throw new DomainException('Execute as migrations para habilitar a sincronização do catálogo PlayFiver.');
        }

        $baseUrl = rtrim((string)($this->config->publicConfig()['base_url'] ?? 'https://api.playfivers.com'), '/');
        $providersPayload = $this->requestFirst($baseUrl, [
            '/api/v1/providers',
            '/v1/providers',
            '/api/providers',
            '/providers',
        ]);
        $gamesPayload = $this->requestFirst($baseUrl, [
            '/api/v1/games',
            '/v1/games',
            '/api/games',
            '/games',
        ]);

        $providers = $this->extractList($providersPayload, ['providers', 'data.providers', 'data.items', 'items', 'data']);
        $games = $this->extractList($gamesPayload, ['games', 'data.games', 'data.items', 'items', 'data']);
        if (!$providers) throw new DomainException('A PlayFiver não retornou provedores para sincronizar.');
        if (!$games) throw new DomainException('A PlayFiver não retornou jogos para sincronizar.');

        $pdo = Database::connection();
        $providerCount = 0;
        $gameCount = 0;
        $skippedGames = 0;
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $pdo->beginTransaction();
        try {
            // Somente itens sincronizados pela API podem ser desativados por ausência no catálogo remoto.
            $pdo->exec("UPDATE casino_games SET enabled=0 WHERE api_source='PLAYFIVER'");
            $pdo->exec("UPDATE casino_providers SET enabled=0 WHERE api_source='PLAYFIVER'");

            $providerUpsert = $pdo->prepare(
                "INSERT INTO casino_providers(code,name,enabled,mode,api_source)\n" .
                "VALUES(:code,:name,:enabled,'DEMO','PLAYFIVER')\n" .
                "ON DUPLICATE KEY UPDATE name=VALUES(name),enabled=VALUES(enabled),api_source='PLAYFIVER'"
            );
            $providerLookup = $pdo->prepare('SELECT id FROM casino_providers WHERE code=:code LIMIT 1');

            $providerIds = [];
            foreach ($providers as $provider) {
                if (!is_array($provider)) continue;
                $remoteCode = trim((string)($provider['code'] ?? $provider['provider_code'] ?? $provider['slug'] ?? ''));
                if ($remoteCode === '') continue;
                $code = $this->normalizeProviderCode($remoteCode);
                if ($code === '') continue;
                $name = trim((string)($provider['name'] ?? $provider['provider_name'] ?? $remoteCode));
                $name = $name !== '' ? mb_substr($name, 0, 120) : strtoupper($code);
                $enabled = $this->remoteEnabled($provider['status'] ?? $provider['enabled'] ?? 1);
                $providerUpsert->execute(['code'=>$code,'name'=>$name,'enabled'=>$enabled]);
                $providerLookup->execute(['code'=>$code]);
                $id = (int)$providerLookup->fetchColumn();
                if ($id > 0) {
                    $providerIds[strtolower($remoteCode)] = $id;
                    $providerIds[strtolower($code)] = $id;
                    $providerCount++;
                }
            }

            // Alguns catálogos podem trazer jogos de provedores não listados no endpoint de provedores.
            $ensureProvider = $pdo->prepare(
                "INSERT INTO casino_providers(code,name,enabled,mode,api_source)\n" .
                "VALUES(:code,:name,1,'DEMO','PLAYFIVER')\n" .
                "ON DUPLICATE KEY UPDATE name=VALUES(name),api_source='PLAYFIVER'"
            );

            $gameUpsert = $pdo->prepare(
                "INSERT INTO casino_games(provider_id,external_id,name,category,image_url,enabled,featured,sort_order,access_count,api_source,source_type,source_distribution,source_original,last_synced_at)\n" .
                "VALUES(:provider_id,:external_id,:name,:category,:image_url,:enabled,0,100,0,'PLAYFIVER',:source_type,:source_distribution,:source_original,:last_synced_at)\n" .
                "ON DUPLICATE KEY UPDATE name=VALUES(name),category=VALUES(category),image_url=VALUES(image_url),enabled=VALUES(enabled),api_source='PLAYFIVER',source_type=VALUES(source_type),source_distribution=VALUES(source_distribution),source_original=VALUES(source_original),last_synced_at=VALUES(last_synced_at)"
            );

            foreach ($games as $game) {
                if (!is_array($game)) { $skippedGames++; continue; }
                $remoteProvider = trim((string)($game['provider'] ?? $game['provider_code'] ?? $game['providerCode'] ?? ''));
                $externalId = trim((string)($game['game_code'] ?? $game['gameCode'] ?? $game['code'] ?? $game['id'] ?? ''));
                $name = trim((string)($game['game_name'] ?? $game['gameName'] ?? $game['name'] ?? ''));
                if ($remoteProvider === '' || $externalId === '' || $name === '') { $skippedGames++; continue; }

                $key = strtolower($remoteProvider);
                $providerId = (int)($providerIds[$key] ?? 0);
                if ($providerId <= 0) {
                    $code = $this->normalizeProviderCode($remoteProvider);
                    if ($code === '') { $skippedGames++; continue; }
                    $ensureProvider->execute(['code'=>$code,'name'=>mb_substr($remoteProvider,0,120)]);
                    $providerLookup->execute(['code'=>$code]);
                    $providerId = (int)$providerLookup->fetchColumn();
                    if ($providerId <= 0) { $skippedGames++; continue; }
                    $providerIds[$key] = $providerId;
                    $providerIds[strtolower($code)] = $providerId;
                    $providerCount++;
                }

                $type = trim((string)($game['game_type'] ?? $game['gameType'] ?? $game['type'] ?? 'slot'));
                $image = trim((string)($game['img_url'] ?? $game['image_url'] ?? $game['image'] ?? $game['icon'] ?? ''));
                if ($image !== '' && (!filter_var($image, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($image), 'https://'))) $image = '';
                $enabled = $this->remoteEnabled($game['status'] ?? $game['enabled'] ?? 1);
                $externalId = mb_substr($externalId, 0, 190);
                $name = mb_substr($name, 0, 190);

                $gameUpsert->execute([
                    'provider_id'=>$providerId,
                    'external_id'=>$externalId,
                    'name'=>$name,
                    'category'=>$this->mapCategory($type),
                    'image_url'=>$image !== '' ? mb_substr($image,0,500) : null,
                    'enabled'=>$enabled,
                    'source_type'=>mb_substr($type,0,60),
                    'source_distribution'=>mb_substr(trim((string)($game['distribution'] ?? '')),0,60) ?: null,
                    'source_original'=>mb_substr(trim((string)($game['original'] ?? '')),0,20) ?: null,
                    'last_synced_at'=>$now,
                ]);
                $gameCount++;
            }

            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }

        return [
            'providers_synced'=>$providerCount,
            'games_synced'=>$gameCount,
            'games_skipped'=>$skippedGames,
            'base_url'=>$baseUrl,
            'synced_at'=>$now,
        ];
    }

    private function requestFirst(string $baseUrl, array $paths): array
    {
        $lastError = 'Endpoint indisponível.';
        foreach ($paths as $path) {
            try {
                return $this->request($baseUrl . $path);
            } catch (\Throwable $error) {
                $lastError = $error->getMessage();
            }
        }
        throw new DomainException('Não foi possível consultar o catálogo PlayFiver: ' . $lastError);
    }

    private function request(string $url): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('Extensão cURL do PHP não está habilitada.');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>12,
            CURLOPT_TIMEOUT=>90,
            CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: MZ90-Catalog-Sync/1.0'],
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($raw === false) throw new RuntimeException('Falha de rede: '.$error);
        if ($status < 200 || $status >= 300) throw new RuntimeException('HTTP '.$status.' em '.$url);
        if (strlen((string)$raw) > 30 * 1024 * 1024) throw new RuntimeException('Resposta do catálogo excedeu o limite de 30 MB.');
        $decoded = json_decode((string)$raw, true, 512, JSON_BIGINT_AS_STRING);
        if (!is_array($decoded)) throw new RuntimeException('Resposta inválida (esperado JSON; recebido '.($contentType ?: 'conteúdo desconhecido').').');
        return $decoded;
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

    private function remoteEnabled(mixed $value): int
    {
        if (is_bool($value)) return $value ? 1 : 0;
        $v = strtolower(trim((string)$value));
        return in_array($v, ['1','true','active','enabled','online','on'], true) ? 1 : 0;
    }

    private function mapCategory(string $type): string
    {
        $value = strtolower(trim($type));
        if (str_contains($value, 'fish') || str_contains($value, 'pool')) return 'OTHER';
        if (str_contains($value, 'sport')) return 'LIVE';
        if (str_contains($value, 'roulette') || str_contains($value, 'table') || str_contains($value, 'blackjack') || str_contains($value, 'baccarat')) return 'TABLE';
        if (str_contains($value, 'live')) return 'LIVE';
        if (str_contains($value, 'slot')) return 'SLOTS';
        return 'OTHER';
    }

    private function gameSourceColumnsExist(): bool
    {
        try {
            $stmt = Database::connection()->query("SHOW COLUMNS FROM casino_games LIKE 'api_source'");
            return (bool)$stmt->fetch();
        } catch (\Throwable) { return false; }
    }
}
